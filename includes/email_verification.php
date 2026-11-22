<?php
/**
 * Email verification — proving a TutorMind account's address belongs to whoever
 * holds the account (migrations/023_email_verification.php).
 *
 * Everything here degrades to "do nothing" when migration 023 hasn't run, so the
 * code can go out before the migration does.
 */
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_template.php';   // renderEmail(), appBaseUrl()

const EMAIL_VERIFY_TTL_HOURS = 24;
const EMAIL_VERIFY_MAX_PER_HOUR = 3;   // per account — extra requests are silently not sent

/** True once users.email_verified_at and the email_verifications table exist. */
function emailVerificationReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $ready = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'")->fetch()
              && (bool)$pdo->query("SHOW TABLES LIKE 'email_verifications'")->fetch();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Email a single-use verification link for the user's current address.
 * Never throws; returns false when nothing was sent.
 */
function sendVerificationEmail(PDO $pdo, int $userId, string $email, string $name): bool
{
    try {
        if (!emailVerificationReady($pdo)) return false;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_verifications WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() >= EMAIL_VERIFY_MAX_PER_HOUR) return false;

        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$userId]); // one live link at a time
        $pdo->prepare("INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL " . EMAIL_VERIFY_TTL_HOURS . " HOUR))")
            ->execute([$userId, hash('sha256', $token)]);

        $link = appBaseUrl() . '/verify-email?token=' . $token;
        $hours = EMAIL_VERIFY_TTL_HOURS;
        $html = renderEmail([
            'preheader'  => "Confirm this is your email address. The link works once, for {$hours} hours.",
            'kicker'     => 'Confirm your email',
            'heading'    => 'Confirm your email address',
            'paragraphs' => [
                "Hi {$name},",
                'Tap the button to confirm that this address is yours. It keeps your TutorMind account safe and means we can reach you if you ever lose your password.',
            ],
            'button'     => ['label' => 'Confirm my email', 'url' => $link],
            'after'      => "The link works once, for {$hours} hours. If you didn't create a TutorMind account or change your email, you can ignore this message.",
            'footer'     => "You're getting this because {$email} was used for a TutorMind account.",
        ]);
        $text = "Hi {$name},\n\nOpen this link to confirm that this email address is yours (it works once, for {$hours} hours):\n\n{$link}\n\n"
              . "If you didn't create a TutorMind account or change your email, ignore this message.\n";

        if (!sendEmail($email, $name, 'Confirm your TutorMind email', $html, $text)) {
            error_log("Verification email failed for user {$userId}");
            return false;
        }
        return true;
    } catch (Throwable $e) {
        error_log('Verification email error: ' . $e->getMessage());
        return false;
    }
}
