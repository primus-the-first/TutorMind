<?php
/**
 * Per-IP sign-up throttle (table: registration_attempts, migrations/025_session_invalidation.php).
 *
 * Every sign-up costs an Argon2id hash, and accounts are what the per-user API
 * quotas hang off, so cheap bulk account creation is the thing to make expensive.
 * An IP gets SIGNUP_MAX_PER_HOUR sign-ups an hour and SIGNUP_MAX_PER_DAY a day.
 * Counted with MySQL's clock (NOW()), same as the table's created_at default.
 *
 * Fails open: if the table is missing (code deployed before the migration) or the DB
 * errors, the sign-up goes ahead and the problem is logged.
 */

const SIGNUP_MAX_PER_HOUR = 5;
const SIGNUP_MAX_PER_DAY = 20;

/** Pure decision: have this IP's recent sign-ups used up its allowance? */
function tm_signup_over_limit(int $lastHour, int $lastDay, int $maxPerHour = SIGNUP_MAX_PER_HOUR, int $maxPerDay = SIGNUP_MAX_PER_DAY): bool
{
    return $lastHour >= $maxPerHour || $lastDay >= $maxPerDay;
}

/**
 * Count this IP's recent attempts; if it still has allowance, record this one.
 * Returns true when the sign-up must be refused (429). Call for every sign-up that
 * passed validation, including ones about to be rejected as duplicates.
 */
function tm_signup_throttled(PDO $pdo, string $ip): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)), 0), COUNT(*)
             FROM registration_attempts
             WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        $stmt->execute([$ip]);
        [$lastHour, $lastDay] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0]);
        if (tm_signup_over_limit($lastHour, $lastDay)) return true;

        $pdo->prepare("INSERT INTO registration_attempts (ip_address) VALUES (?)")->execute([$ip]);

        // Housekeeping: nothing older than the day window matters; ~1 in 100 sign-ups tidies up
        if (random_int(1, 100) === 1) {
            $pdo->exec("DELETE FROM registration_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
        }
    } catch (Throwable $e) {
        error_log('Sign-up throttle unavailable, allowing: ' . $e->getMessage());
    }
    return false;
}
