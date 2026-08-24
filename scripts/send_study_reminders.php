<?php
/**
 * Study reminder batch job — run daily via Windows Task Scheduler:
 *   php.exe C:\xampp\htdocs\TutorMind\scripts\send_study_reminders.php
 *
 * CLI only. For each user with notifications_enabled = 1, checks how long
 * they've been inactive (based on their conversations) against their chosen
 * notification_frequency, and sends a reminder if due — via real Web Push
 * (see sendReminderPush()) plus a stubbed email log (see sendReminderEmail()).
 *
 * TODO: sendReminderEmail() is currently a stub that only logs to
 * logs/reminders.log. Replace with real delivery (e.g. PHPMailer + SMTP,
 * credentials in includes/config-sql.ini under a new [smtp] section,
 * mirroring the [database] section pattern in includes/db_mysql.php)
 * once an email provider is chosen.
 */

if (php_sapi_name() !== 'cli') {
    exit("This script is CLI only.\n");
}

// XAMPP on Windows ships without openssl.cnf wired up, which breaks the EC
// key operations Web Push signing needs (VAPID JWTs). Point OpenSSL at the
// bundled config if nothing else already set one. No-op in production
// (Linux/cPanel), where OpenSSL is normally configured correctly already.
if (!getenv('OPENSSL_CONF') && PHP_OS_FAMILY === 'Windows') {
    $winOpensslCnf = 'C:\\xampp\\php\\extras\\openssl\\openssl.cnf';
    if (file_exists($winOpensslCnf)) {
        putenv('OPENSSL_CONF=' . $winOpensslCnf);
    }
}

require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/../includes/webpush_config.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Frequency -> minimum days inactive before reminding, and minimum days between reminders.
$FREQUENCY_RULES = [
    'daily'        => ['inactive_days' => 1, 'cooldown_days' => 1],
    'weekdays'     => ['inactive_days' => 2, 'cooldown_days' => 2],
    'weekends'     => ['inactive_days' => 7, 'cooldown_days' => 7],
    'three_weekly' => ['inactive_days' => 2, 'cooldown_days' => 2],
    'weekly'       => ['inactive_days' => 7, 'cooldown_days' => 7],
];

/**
 * STUB — logs what would be sent instead of actually emailing.
 * TODO: replace with PHPMailer + SMTP once a provider is chosen.
 */
function sendReminderEmail(array $user, int $daysInactive): void {
    $line = sprintf(
        "[%s] STUB SEND -> user_id=%d email=%s name=%s days_inactive=%d frequency=%s\n",
        date('Y-m-d H:i:s'),
        $user['id'],
        $user['email'],
        $user['first_name'] ?? '',
        $daysInactive,
        $user['notification_frequency']
    );

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/reminders.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Queues a real Web Push notification for every device the user has
 * subscribed on. Returns the queued subscriptions keyed by endpoint hash,
 * so the caller can clean up any that come back expired/revoked after flush.
 */
function queueReminderPush($webPush, PDO $pdo, array $user, int $daysInactive): array {
    $stmt = $pdo->prepare("SELECT endpoint, endpoint_hash, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $subs = $stmt->fetchAll();

    $payload = json_encode([
        'title' => 'Time to study!',
        'body'  => "You've been away for {$daysInactive} day(s) — come keep your streak going.",
    ]);

    $queued = [];
    foreach ($subs as $sub) {
        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint'  => $sub['endpoint'],
            'publicKey' => $sub['p256dh'],
            'authToken' => $sub['auth'],
        ]);
        $webPush->queueNotification($subscription, $payload);
        $queued[$sub['endpoint_hash']] = true;
    }
    return $queued;
}

try {
    $pdo = getDbConnection();

    $webPush = null;
    if (class_exists('Minishlink\\WebPush\\WebPush')) {
        $webPushConfig = getWebPushConfig();
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => $webPushConfig['vapid_subject'],
                'publicKey'  => $webPushConfig['vapid_public_key'],
                'privateKey' => $webPushConfig['vapid_private_key'],
            ],
        ]);
    }
    $queuedEndpointHashes = [];

    $stmt = $pdo->query(
        "SELECT id, email, first_name, notification_frequency, last_reminder_sent_at
         FROM users
         WHERE notifications_enabled = 1
           AND email IS NOT NULL AND email != ''
           AND notification_frequency IN ('daily', 'weekdays', 'weekends', 'three_weekly', 'weekly')"
    );
    $users = $stmt->fetchAll();

    $now = new DateTime();
    $remindedCount = 0;

    foreach ($users as $user) {
        $rules = $FREQUENCY_RULES[$user['notification_frequency']];

        // Cooldown: skip if already reminded within the window for this frequency.
        if ($user['last_reminder_sent_at']) {
            $daysSinceReminder = $now->diff(new DateTime($user['last_reminder_sent_at']))->days;
            if ($daysSinceReminder < $rules['cooldown_days']) {
                continue;
            }
        }

        // Last activity, following the conversations-table convention used in api/analytics.php.
        $actStmt = $pdo->prepare("SELECT MAX(created_at) AS last_active FROM conversations WHERE user_id = ?");
        $actStmt->execute([$user['id']]);
        $lastActive = $actStmt->fetchColumn();

        $daysInactive = $lastActive
            ? $now->diff(new DateTime($lastActive))->days
            : PHP_INT_MAX; // Never studied — always eligible.

        if ($daysInactive < $rules['inactive_days']) {
            continue; // Still active enough, skip.
        }

        sendReminderEmail($user, $daysInactive);

        if ($webPush !== null) {
            $queuedEndpointHashes += queueReminderPush($webPush, $pdo, $user, $daysInactive);
        }

        $upd = $pdo->prepare("UPDATE users SET last_reminder_sent_at = NOW() WHERE id = ?");
        $upd->execute([$user['id']]);
        $remindedCount++;
    }

    $pushSent = 0;
    if ($webPush !== null && !empty($queuedEndpointHashes)) {
        $delStmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?");
        foreach ($webPush->flush() as $report) {
            $endpointHash = hash('sha256', $report->getEndpoint());
            if ($report->isSuccess()) {
                $pushSent++;
            } elseif ($report->isSubscriptionExpired()) {
                // Browser revoked/expired this subscription — normal, clean it up.
                $delStmt->execute([$endpointHash]);
            } else {
                error_log("Push send failed for endpoint {$report->getEndpoint()}: " . $report->getReason());
            }
        }
    }

    echo "Reminder run complete. {$remindedCount} of " . count($users) . " eligible users reminded ({$pushSent} push notification(s) delivered).\n";
} catch (Exception $e) {
    fwrite(STDERR, "Reminder run failed: " . $e->getMessage() . "\n");
    exit(1);
}
