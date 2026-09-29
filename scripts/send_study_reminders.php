<?php
/**
 * Study reminder batch job — run once a day (cPanel cron in production):
 *   php scripts/send_study_reminders.php              send what's due
 *   php scripts/send_study_reminders.php --dry-run    show who would get what; sends nothing, changes nothing
 *   php scripts/send_study_reminders.php --user=42    only this user, ignoring schedule/cooldown (testing)
 *
 * Who: notifications_enabled = 1 and a reminder schedule (notification_frequency).
 * Channels, each only if the student has it:
 *   - email: email_reminders = 1 (opt-in, migration 019), via Brevo (includes/mailer.php),
 *            with a one-click unsubscribe link + List-Unsubscribe headers
 *   - push:  their push_subscriptions (Web Push)
 * A student with neither channel is skipped and their cooldown isn't touched.
 *
 * The reminder points at something real: their newest unfinished session and its
 * next milestone, a streak worth keeping, missed quiz questions. No guilt trips.
 * Dates come from MySQL's clock, like api/analytics.php (PHP and MySQL time zones differ).
 */

if (php_sapi_name() !== 'cli') {
    exit("This script is CLI only.\n");
}

// XAMPP on Windows ships without openssl.cnf wired up, which breaks the EC
// key operations Web Push signing needs (VAPID JWTs). No-op in production.
if (!getenv('OPENSSL_CONF') && PHP_OS_FAMILY === 'Windows') {
    $winOpensslCnf = 'C:\\xampp\\php\\extras\\openssl\\openssl.cnf';
    if (file_exists($winOpensslCnf)) putenv('OPENSSL_CONF=' . $winOpensslCnf);
}

require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/../includes/webpush_config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_template.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';

$opts = getopt('', ['dry-run', 'user:']);
$DRY_RUN = isset($opts['dry-run']);
$ONLY_USER = isset($opts['user']) ? (int)$opts['user'] : null;
const MAX_EMAILS_PER_RUN = 250;   // Brevo free plan: 300/day, and password resets need some too

// Schedule → minimum days since last study, days between reminders, and allowed weekdays (ISO 1=Mon … 7=Sun)
$SCHEDULES = [
    'daily'        => ['inactive' => 1, 'cooldown' => 1, 'days' => [1, 2, 3, 4, 5, 6, 7], 'label' => 'daily'],
    'weekdays'     => ['inactive' => 1, 'cooldown' => 1, 'days' => [1, 2, 3, 4, 5],       'label' => 'on weekdays'],
    'weekends'     => ['inactive' => 1, 'cooldown' => 1, 'days' => [6, 7],                'label' => 'at weekends'],
    'three_weekly' => ['inactive' => 2, 'cooldown' => 2, 'days' => [1, 2, 3, 4, 5, 6, 7], 'label' => 'every few days'],
    'weekly'       => ['inactive' => 7, 'cooldown' => 7, 'days' => [1, 2, 3, 4, 5, 6, 7], 'label' => 'weekly'],
];

/** What to point the student at: newest unfinished session, streak, missed questions. */
function studyContext(PDO $pdo, int $userId, string $dbToday): array
{
    $stmt = $pdo->prepare("SELECT id, title, context_data FROM conversations WHERE user_id = ? ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 10");
    $stmt->execute([$userId]);
    $continue = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cd = !empty($c['context_data']) ? json_decode($c['context_data'], true) : null;
        $ms = is_array($cd['outline']['milestones'] ?? null) ? $cd['outline']['milestones'] : [];
        $next = null;
        foreach ($ms as $m) { if (empty($m['completed'])) { $next = $m['title'] ?? null; break; } }
        $candidate = ['id' => (int)$c['id'], 'title' => $c['title'] ?: 'your last session',
                      'topic' => trim((string)($cd['topic'] ?? '')) ?: null, 'next' => $next];
        if (!$ms || $next) { $continue = $candidate; break; }   // unfinished (or no outline)
        $continue = $continue ?? $candidate;                     // all finished: fall back to the newest
    }

    // Consecutive study days ending yesterday — the streak today's session would extend
    $stmt = $pdo->prepare("SELECT DISTINCT DATE(created_at) FROM conversations WHERE user_id = ? ORDER BY 1 DESC LIMIT 60");
    $stmt->execute([$userId]);
    $dates = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    $streak = 0;
    $d = (new DateTime($dbToday))->modify('-1 day');
    while (isset($dates[$d->format('Y-m-d')])) { $streak++; $d->modify('-1 day'); }

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT question) FROM recall_quizzes WHERE user_id = ? AND answered_at IS NOT NULL AND score < 0.7 AND answered_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)");
    $stmt->execute([$userId]);

    return ['continue' => $continue, 'streak' => $streak, 'missed' => (int)$stmt->fetchColumn()];
}

function shorten(string $s, int $max = 60): string
{
    return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
}

/** Subject, email HTML/text and push text for one student. */
function buildReminder(array $user, ?int $daysInactive, array $ctx, string $scheduleLabel, string $unsubscribeUrl): array
{
    $base = appBaseUrl();
    $name = $user['first_name'] ?: $user['username'];
    $c = $ctx['continue'];
    $never = $daysInactive === null || !$c;

    if ($never) {
        $subject = 'Your first TutorMind session is waiting';
        $heading = 'Ready for your first session?';
        $lines = ["Hi {$name},", 'Bring anything you are working on, like homework, a test coming up or something you are curious about. TutorMind works through it with you step by step.'];
        $panel = null;
        $button = ['label' => 'Start a session', 'url' => $base . '/chat'];
        $push = 'Your first session is waiting. Bring anything you are working on.';
    } else {
        $subject = 'Pick up where you left off: ' . shorten($c['title']);
        $heading = 'Pick up where you left off';
        $lines = ["Hi {$name},"];
        $lines[] = $daysInactive <= 1
            ? 'You studied yesterday. A short session today keeps the momentum going.'
            : "It has been {$daysInactive} days since your last session. Ten minutes is enough to get back into it.";
        if ($ctx['streak'] > 0 && $daysInactive <= 1) {
            $lines[] = "Study today to make your streak " . ($ctx['streak'] + 1) . " days.";
        }
        $panel = [
            'label' => $c['next'] ? 'Next up' . ($c['topic'] ? ' in ' . ucwords($c['topic']) : '') : 'Your last session',
            'text'  => $c['next'] ?: $c['title'],
        ];
        $button = ['label' => 'Continue session', 'url' => $base . '/chat/' . $c['id']];
        $push = $daysInactive <= 1
            ? 'Pick up where you left off: ' . shorten($c['title'], 50)
            : "It's been {$daysInactive} days. Pick up where you left off: " . shorten($c['title'], 40);
    }

    $after = $ctx['missed'] > 0
        ? ($ctx['missed'] === 1 ? 'There is also 1 quiz question' : "There are also {$ctx['missed']} quiz questions") . ' worth another look on your dashboard.'
        : null;

    $html = renderEmail([
        'preheader'   => $never ? 'Bring anything you are working on.' : ($panel['label'] . ': ' . $panel['text']),
        'kicker'      => 'Your study reminder',
        'heading'     => $heading,
        'paragraphs'  => $lines,
        'panel'       => $panel,
        'button'      => $button,
        'after'       => $after,
        'fallback'    => false,
        'footer'      => "You're getting this because you asked TutorMind to email you study reminders {$scheduleLabel}.",
        'unsubscribe' => $unsubscribeUrl,
    ]);
    $text = implode("\n\n", $lines)
        . ($panel ? "\n\n{$panel['label']}: {$panel['text']}" : '')
        . "\n\n{$button['label']}: {$button['url']}"
        . ($after ? "\n\n{$after}" : '')
        . "\n\n--\nYou're getting this because you asked TutorMind to email you study reminders {$scheduleLabel}.\nUnsubscribe: {$unsubscribeUrl}\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text, 'push' => $push];
}

try {
    $pdo = getDbConnection();
    [$dbToday, $dbNow, $isoDow] = $pdo->query("SELECT CURDATE(), NOW(), WEEKDAY(CURDATE()) + 1")->fetch(PDO::FETCH_NUM);

    $webPush = null;
    if (class_exists('Minishlink\\WebPush\\WebPush')) {
        $cfg = getWebPushConfig();
        $webPush = new \Minishlink\WebPush\WebPush(['VAPID' => [
            'subject' => $cfg['vapid_subject'], 'publicKey' => $cfg['vapid_public_key'], 'privateKey' => $cfg['vapid_private_key'],
        ]]);
    }

    $sql = "SELECT id, email, username, first_name, notification_frequency, last_reminder_sent_at, email_reminders, email_token
            FROM users
            WHERE notifications_enabled = 1 AND notification_frequency IN ('" . implode("','", array_keys($SCHEDULES)) . "')";
    $params = [];
    if ($ONLY_USER !== null) { $sql .= " AND id = ?"; $params[] = $ONLY_USER; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subStmt = $pdo->prepare("SELECT endpoint, endpoint_hash, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
    $lastStmt = $pdo->prepare("SELECT MAX(created_at) FROM conversations WHERE user_id = ?");
    $tokenStmt = $pdo->prepare("UPDATE users SET email_token = ? WHERE id = ? AND email_token IS NULL");
    $doneStmt = $pdo->prepare("UPDATE users SET last_reminder_sent_at = NOW() WHERE id = ?");

    $stats = ['due' => 0, 'emails' => 0, 'email_failed' => 0, 'push_queued' => 0, 'skipped_no_channel' => 0];
    foreach ($users as $user) {
        $rule = $SCHEDULES[$user['notification_frequency']];

        if ($ONLY_USER === null) {
            if (!in_array((int)$isoDow, $rule['days'], true)) continue;
            if ($user['last_reminder_sent_at']
                && (strtotime($dbNow) - strtotime($user['last_reminder_sent_at'])) < ($rule['cooldown'] * 86400 - 3600)) continue; // 1h slack for cron drift
        }

        $lastStmt->execute([$user['id']]);
        $lastActive = $lastStmt->fetchColumn();
        $daysInactive = $lastActive ? (int)floor((strtotime($dbToday) - strtotime(substr($lastActive, 0, 10))) / 86400) : null;
        if ($ONLY_USER === null && $daysInactive !== null && $daysInactive < $rule['inactive']) continue; // studied recently enough

        $subStmt->execute([$user['id']]);
        $subs = $webPush ? $subStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $wantsEmail = (int)$user['email_reminders'] === 1 && !empty($user['email']);
        if (!$wantsEmail && !$subs) { $stats['skipped_no_channel']++; continue; }
        $stats['due']++;

        if ($wantsEmail && !$user['email_token'] && !$DRY_RUN) {
            $tokenStmt->execute([bin2hex(random_bytes(16)), $user['id']]);
            $user['email_token'] = $pdo->query("SELECT email_token FROM users WHERE id = " . (int)$user['id'])->fetchColumn();
        }
        $unsubscribe = appBaseUrl() . '/unsubscribe?u=' . $user['id'] . '&t=' . ($user['email_token'] ?: 'DRYRUN');
        $msg = buildReminder($user, $daysInactive, studyContext($pdo, (int)$user['id'], $dbToday), $rule['label'], $unsubscribe);

        $channels = [];
        if ($wantsEmail) {
            if ($stats['emails'] >= MAX_EMAILS_PER_RUN) {
                $channels[] = 'email:CAPPED';
            } elseif ($DRY_RUN) {
                $channels[] = 'email';
            } elseif (sendEmail($user['email'], $user['first_name'] ?: $user['username'], $msg['subject'], $msg['html'], $msg['text'], [
                'List-Unsubscribe' => '<' . $unsubscribe . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ])) {
                $stats['emails']++; $channels[] = 'email';
            } else {
                $stats['email_failed']++; $channels[] = 'email:FAILED';
            }
        }
        if ($subs) {
            $channels[] = 'push×' . count($subs);
            if (!$DRY_RUN) {
                $payload = json_encode(['title' => 'Time to study', 'body' => $msg['push']]);
                foreach ($subs as $sub) {
                    $webPush->queueNotification(\Minishlink\WebPush\Subscription::create([
                        'endpoint' => $sub['endpoint'], 'publicKey' => $sub['p256dh'], 'authToken' => $sub['auth'],
                    ]), $payload);
                    $stats['push_queued']++;
                }
            }
        }

        if (!$DRY_RUN) $doneStmt->execute([$user['id']]);
        printf("%s user %d (%s, %s): %s — \"%s\"\n", $DRY_RUN ? '[dry-run]' : 'sent', $user['id'], $user['notification_frequency'],
            $daysInactive === null ? 'never studied' : "{$daysInactive}d since last study", implode(', ', $channels), $msg['subject']);
    }

    $pushSent = 0;
    if ($webPush && $stats['push_queued'] > 0) {
        $del = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?");
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) $pushSent++;
            elseif ($report->isSubscriptionExpired()) $del->execute([hash('sha256', $report->getEndpoint())]); // revoked: clean up
            else error_log("Push send failed for endpoint {$report->getEndpoint()}: " . $report->getReason());
        }
    }

    printf("%sReminder run complete: %d due, %d email(s) sent%s, %d push delivered, %d skipped (no channel on).\n",
        $DRY_RUN ? '[dry-run] ' : '', $stats['due'], $stats['emails'],
        $stats['email_failed'] ? " ({$stats['email_failed']} failed)" : '', $pushSent, $stats['skipped_no_channel']);
} catch (Throwable $e) {
    fwrite(STDERR, "Reminder run failed: " . $e->getMessage() . "\n");
    exit(1);
}
