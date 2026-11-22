<?php
/**
 * Daily per-user quotas for the paid APIs (text-to-speech, image generation,
 * group study AI, quiz AI, and the small chat helpers).
 *
 * Why: registration is open, so one scripted account could otherwise run up the
 * ElevenLabs / Imagen / DeepSeek / Gemini bills. The chat reply has its own
 * 15-per-minute limit (checkChatRateLimit); these are per-DAY caps per feature.
 *
 * How: one row per (user, feature, day) in api_usage (migrations/024_api_usage.php).
 * consumeQuota() adds the cost with a single atomic upsert, reads the count back,
 * and gives the cost back if that went over the limit. The day is MySQL's CURDATE(),
 * not PHP's date(): PHP and MySQL run in different time zones here, and one clock
 * has to decide both "which day is this" and "how long until midnight".
 *
 * Use it AFTER the request is validated and BEFORE the provider call, so a bad
 * request never costs a quota point:
 *
 *     requireQuota($pdo, $userId, 'quiz_generate');            // answers 429 and exits when over
 *     $q = consumeQuota($pdo, $userId, 'image');               // when exit would break the reply
 *     if (!$q['allowed']) { ...friendly message... }
 *
 * Fails OPEN: if the table is missing (code deployed before the migration ran) or
 * the database errors, the request is allowed and one line goes to the error log
 * per request. Quotas are a cost guard, not a security boundary, and a quota bug
 * must not take the whole feature down.
 *
 * Tuning: edit the defaults in quotaLimits(), or add a [quotas] section to
 * includes/config-sql.ini (local) / config.ini (production), e.g.
 *     [quotas]
 *     tts = 300
 *     image = 30
 * A value of 0 switches that feature off for everyone.
 */

/** Default allowance per user per day. Keys are the `kind` passed to consumeQuota(). */
function quotaDefaults(): array
{
    return [
        'tts'           => 150,  // ElevenLabs / inference.sh speech (api/tts.php)
        'image'         => 15,   // Imagen, the priciest call: api/image.php + the chat's generate_image tool
        'group_ai'      => 300,  // Q replies, huddle rounds and recaps (a busy room hour is ~60-100)
        'quiz_generate' => 40,   // api/quiz.php generate
        'quiz_grade'    => 200,  // api/quiz.php grade (open-ended answers only; recognition is free)
        'suggestions'   => 150,  // generate_suggestions (home-screen prompt pills)
        'title'         => 150,  // generate_title (names a new chat)
        'interests'     => 100,  // detect_interests
    ];
}

/** Defaults with any [quotas] overrides from the config file applied. */
function quotaLimits(): array
{
    static $limits = null;
    if ($limits !== null) return $limits;

    $limits = quotaDefaults();
    // Same file lookup as appBaseUrl(): the local file when it exists, else production's
    foreach (['config-sql.ini', 'config.ini'] as $file) {
        $path = __DIR__ . '/' . $file;
        if (!file_exists($path)) continue;
        $cfg = @parse_ini_file($path, true);
        if (is_array($cfg) && isset($cfg['quotas']) && is_array($cfg['quotas'])) {
            foreach ($cfg['quotas'] as $kind => $value) {
                // Only known features, only whole numbers >= 0; anything else is a typo, ignore it
                if (isset($limits[$kind]) && is_numeric($value) && (int) $value >= 0 && (string) (int) $value === trim((string) $value)) {
                    $limits[$kind] = (int) $value;
                }
            }
        }
        break;
    }
    return $limits;
}

/**
 * The four SQL statements, in one place. Everything MySQL-specific lives here (the
 * test harness redefines this function to run on SQLite).
 *
 *   add   (user_id, kind, cost, cost) bumps today's row by cost, creating it
 *         if needed; never goes below 0 (a refund can't push the count negative)
 *   read  (user_id, kind) -> count, secs_left (seconds until the next midnight)
 */
if (!function_exists('quotaSql')) {
    function quotaSql(): array
    {
        return [
            'add' => "INSERT INTO api_usage (user_id, kind, `day`, `count`)
                      VALUES (?, ?, CURDATE(), GREATEST(?, 0))
                      ON DUPLICATE KEY UPDATE `count` = GREATEST(CAST(`count` AS SIGNED) + ?, 0)",
            'read' => "SELECT `count`, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS secs_left
                       FROM api_usage WHERE user_id = ? AND kind = ? AND `day` = CURDATE()",
        ];
    }
}

/** Apply $delta (positive = use, negative = give back) to today's row. Throws on a DB error. */
function quotaApply(PDO $pdo, int $userId, string $kind, int $delta): void
{
    $stmt = $pdo->prepare(quotaSql()['add']);
    if ($stmt === false) throw new RuntimeException('api_usage update failed');
    // Bound as integers: a string '-1' would be compared as text by GREATEST()/MAX() on some drivers
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $kind, PDO::PARAM_STR);
    $stmt->bindValue(3, $delta, PDO::PARAM_INT);
    $stmt->bindValue(4, $delta, PDO::PARAM_INT);
    if ($stmt->execute() === false) throw new RuntimeException('api_usage update failed');
}

/** Log a quota-storage problem once per request, however many calls hit it. */
function quotaLogOnce(Throwable $e): void
{
    static $logged = false;
    if ($logged) return;
    $logged = true;
    $missing = ($e instanceof PDOException) && in_array((string) $e->getCode(), ['42S02'], true);
    error_log($missing
        ? 'usage_quota: api_usage table missing (run migrations/024_api_usage.php); quotas are not enforced until then'
        : 'usage_quota: quota check failed, allowing the request: ' . $e->getMessage());
}

/**
 * Spend $cost of the user's daily allowance for $kind.
 *
 * A negative $cost gives points back (floored at 0) and is always "allowed".
 * Returns ['allowed' => bool, 'used' => int, 'limit' => int, 'retry_after' => int]
 * where retry_after is the seconds until the allowance resets (0 when allowed).
 * When the call would go over the limit nothing is kept: the cost is returned.
 * An unknown $kind is a programming error and throws, so it can't pass unnoticed.
 */
function consumeQuota(PDO $pdo, int $userId, string $kind, int $cost = 1): array
{
    $limits = quotaLimits();
    if (!isset($limits[$kind])) {
        throw new InvalidArgumentException("Unknown quota kind '{$kind}'");
    }
    $limit = $limits[$kind];
    $open = ['allowed' => true, 'used' => 0, 'limit' => $limit, 'retry_after' => 0];

    try {
        quotaApply($pdo, $userId, $kind, $cost);
        if ($cost <= 0) return $open;   // a refund (or a free call) needs no verdict

        $stmt = $pdo->prepare(quotaSql()['read']);
        if ($stmt === false || $stmt->execute([$userId, $kind]) === false) {
            throw new RuntimeException('api_usage read failed');
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('api_usage row missing right after update');

        $used = (int) $row['count'];
        if ($used <= $limit) {
            return ['allowed' => true, 'used' => $used, 'limit' => $limit, 'retry_after' => 0];
        }

        // Over: hand the cost back so a stream of refused requests doesn't keep the counter climbing
        quotaApply($pdo, $userId, $kind, -$cost);
        return [
            'allowed'     => false,
            'used'        => max(0, $used - $cost),
            'limit'       => $limit,
            'retry_after' => max(1, (int) $row['secs_left']),
        ];
    } catch (Throwable $e) {
        quotaLogOnce($e);
        return $open;   // fail open: a broken counter must not break the feature
    }
}

/** The JSON body every quota refusal uses. */
function quotaDeniedPayload(array $quota): array
{
    return [
        'success'     => false,
        'error'       => 'Daily limit reached for this feature. It resets at midnight.',
        'retry_after' => (int) $quota['retry_after'],
    ];
}

/** Spend the quota or answer HTTP 429 and stop. Not for code that must still finish a reply. */
function requireQuota(PDO $pdo, int $userId, string $kind, int $cost = 1): void
{
    $q = consumeQuota($pdo, $userId, $kind, $cost);
    if ($q['allowed']) return;

    if (ob_get_level() > 0) ob_clean();
    if (!headers_sent()) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . (int) $q['retry_after']);
    }
    echo json_encode(quotaDeniedPayload($q));
    exit;
}
