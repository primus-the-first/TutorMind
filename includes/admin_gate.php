<?php
/**
 * Admin gate for feedback data. Being logged in is not enough: registration is open,
 * and feedback rows carry every student's name, username, email and comments.
 *
 * Allowed user IDs come from [app] admin_user_ids in includes/config-sql.ini /
 * config.ini (comma-separated, e.g. admin_user_ids = "1,7"). Key missing or empty =
 * nobody is an admin.
 */

if (!function_exists('feedbackAdminIds')) {
    function feedbackAdminIds(): array
    {
        foreach (['config-sql.ini', 'config.ini'] as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                $cfg = parse_ini_file($path, true);
                $raw = is_array($cfg) ? ($cfg['app']['admin_user_ids'] ?? '') : '';
                return array_values(array_filter(array_map('trim', explode(',', (string)$raw)), 'ctype_digit'));
            }
        }
        return [];
    }
}

if (!function_exists('isFeedbackAdmin')) {
    /** True when the logged-in user (from $_SESSION) is on the admin list. */
    function isFeedbackAdmin(): bool
    {
        return isset($_SESSION['user_id'])
            && in_array((string)$_SESSION['user_id'], feedbackAdminIds(), true);
    }
}
