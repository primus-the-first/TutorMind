<?php
/**
 * Session validity — signing out sessions that outlive a credential change
 * (migrations/025_session_invalidation.php).
 *
 * A PHP session trusts $_SESSION['user_id'] for as long as it lives, so someone who
 * was signed in before a password reset, a password/email change or an account
 * take-over (auth_mysql.php, Google sign-in on an unverified account) stayed signed
 * in. Now:
 *
 *  - every sign-in records $_SESSION['auth_at'], the moment it began;
 *  - those events stamp users.sessions_valid_after = NOW();
 *  - a session whose auth_at is older than the stamp is signed out.
 *
 * Both numbers are epoch seconds read from MySQL (UNIX_TIMESTAMP), so the PHP and
 * MySQL time zones, which differ on this project, can't skew the comparison.
 *
 * It costs nothing until migration 025 has run: a missing column is caught and the
 * app behaves as before, so the code can go out before the migration does.
 */

const TM_AUTH_RECHECK_SECONDS = 60;   // how often a live session re-asks the DB

/**
 * Pure decision: is a session that signed in at $authAt revoked by the stamp?
 * No stamp = nobody has been signed out. A stamp but no auth_at means the session
 * predates this feature, so it can't prove it came after the stamp.
 */
function tm_session_is_stale(?int $validAfter, ?int $authAt): bool
{
    if ($validAfter === null) return false;
    if ($authAt === null) return true;
    return $validAfter > $authAt;
}

/** True for "unknown column / table" (migration 025 not run yet): expected, not worth logging. */
function tm_session_schema_missing(Throwable $e): bool
{
    $code = $e instanceof PDOException && isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    return $code === 1054 || $code === 1146 || $e->getCode() === '42S22' || $e->getCode() === '42S02';
}

/**
 * Record that this session has just signed in. Call after setting $_SESSION['user_id'].
 * auth_at is MySQL's clock (falls back to PHP's if the query fails).
 */
function tm_session_mark_authenticated(?PDO $pdo = null): void
{
    $at = 0;
    try {
        if ($pdo === null) {
            require_once __DIR__ . '/db_mysql.php';
            $pdo = getDbConnection();
        }
        $at = (int)$pdo->query("SELECT UNIX_TIMESTAMP(NOW())")->fetchColumn();
    } catch (Throwable $e) {
        // fall back below
    }
    $_SESSION['auth_at'] = $at > 0 ? $at : time();
    $_SESSION['auth_checked_at'] = time();   // just signed in: no need to re-ask for a minute
}

/**
 * Has this session been signed out by a credential change? Asks the DB at most once
 * per TM_AUTH_RECHECK_SECONDS per session (one indexed lookup by primary key); the
 * rest of the time it's two array reads. Fails open: a DB error or a missing column
 * means "not revoked", as before.
 */
function tm_session_revoked(): bool
{
    if (empty($_SESSION['user_id'])) return false;

    $now = time();
    $checked = (int)($_SESSION['auth_checked_at'] ?? 0);
    if ($checked > 0 && $checked <= $now && $now - $checked < TM_AUTH_RECHECK_SECONDS) return false;
    $_SESSION['auth_checked_at'] = $now;   // set first, so a failing DB isn't retried on every request

    try {
        require_once __DIR__ . '/db_mysql.php';
        $stmt = getDbConnection()->prepare("SELECT UNIX_TIMESTAMP(sessions_valid_after) FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $value = $stmt->fetchColumn();
    } catch (Throwable $e) {
        if (!tm_session_schema_missing($e)) error_log('Session validity check failed: ' . $e->getMessage());
        return false;
    }

    $validAfter = ($value === false || $value === null) ? null : (int)$value;
    $authAt = isset($_SESSION['auth_at']) ? (int)$_SESSION['auth_at'] : null;
    return tm_session_is_stale($validAfter, $authAt);
}

/** Forget who this session was (it keeps working as a logged-out session). */
function tm_session_drop(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
}

/**
 * Sign out every session of this user that started before now (and, unless the
 * caller already did, their "Remember me" logins, which would just sign them back in).
 * The current session, if it's this user's, is re-stamped so it survives; the caller
 * should session_regenerate_id(true) afterwards. Returns false (changing nothing)
 * before migration 025 or on a DB error.
 */
function tm_invalidate_sessions(PDO $pdo, int $userId, bool $restampCurrent = true, bool $dropRememberTokens = true): bool
{
    try {
        $pdo->prepare("UPDATE users SET sessions_valid_after = NOW() WHERE id = ?")->execute([$userId]);
    } catch (Throwable $e) {
        if (!tm_session_schema_missing($e)) error_log('Could not sign out other sessions: ' . $e->getMessage());
        return false;
    }
    if ($dropRememberTokens) {
        try {
            $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?")->execute([$userId]);
        } catch (Throwable $e) {
            error_log('Could not clear remember-me tokens: ' . $e->getMessage());
        }
    }
    if ($restampCurrent && session_status() === PHP_SESSION_ACTIVE && (int)($_SESSION['user_id'] ?? 0) === $userId) {
        tm_session_mark_authenticated($pdo);   // NOW() read after the stamp, so >= it
    }
    return true;
}
