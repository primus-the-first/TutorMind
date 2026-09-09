<?php
/**
 * Widget Library Service
 *
 * Resolves tm-ref fenced blocks (emitted by the AI when it chooses to reuse a
 * pre-validated widget instead of authoring one from scratch — see the
 * "REUSABLE WIDGET LIBRARY" section injected by buildSystemPrompt() in
 * tutor_service.php) into the full tm-<type> widget block the client renders.
 *
 * Composed the same way resolveImageMarkers() (api/services/image_service.php)
 * is already composed after formatResponse():
 *     resolveWidgetLibraryRefs(formatResponse($answer), $pdo)
 * so the client (assets/js/tm-widgets.js) needs zero changes — by the time HTML
 * reaches the browser, a resolved tm-ref is indistinguishable from a normally
 * authored widget block.
 */

if (!function_exists('resolveWidgetLibraryRefs')) {
    function resolveWidgetLibraryRefs(string $html, PDO $pdo): string
    {
        return preg_replace_callback(
            '/<pre><code class="language-tm-ref">([\s\S]*?)<\/code><\/pre>/',
            function ($matches) use ($pdo) {
                $decoded = json_decode(trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8')), true);
                $key = $decoded['key'] ?? null;
                if (!is_string($key) || $key === '') {
                    // Malformed ref — drop it rather than showing broken JSON to the student.
                    return '';
                }

                try {
                    $stmt = $pdo->prepare("SELECT widget_type, payload FROM widget_library WHERE topic_key = ?");
                    $stmt->execute([$key]);
                    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    // Table missing, DB hiccup, etc. — fail soft, never break the page.
                    error_log("widget_library: lookup failed for '{$key}': " . $e->getMessage());
                    return '';
                }

                if (!$entry) {
                    error_log("widget_library: unknown key '{$key}'");
                    return '';
                }

                try {
                    $pdo->prepare("UPDATE widget_library SET usage_count = usage_count + 1 WHERE topic_key = ?")
                        ->execute([$key]);
                } catch (Throwable $e) {
                    // Non-critical — don't let a failed usage-count bump break resolution.
                    error_log("widget_library: usage_count update failed for '{$key}': " . $e->getMessage());
                }

                $type = htmlspecialchars($entry['widget_type'], ENT_QUOTES, 'UTF-8');
                $payload = htmlspecialchars($entry['payload'], ENT_QUOTES, 'UTF-8');
                return '<pre><code class="language-tm-' . $type . '">' . $payload . '</code></pre>';
            },
            $html
        );
    }
}
