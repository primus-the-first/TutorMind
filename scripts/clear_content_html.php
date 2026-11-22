<?php
/**
 * Clear the cached HTML (messages.content_html) so every message is re-rendered from its
 * raw text on next load. Needed once after the response formatter went to Parsedown safe
 * mode + escaped LaTeX: rows cached before that may hold unsanitised model HTML.
 * get_conversation re-renders and re-caches any message whose content_html is empty.
 *
 *   php scripts/clear_content_html.php          count what would be cleared (changes nothing)
 *   php scripts/clear_content_html.php --apply  set content_html = NULL
 */
if (php_sapi_name() !== 'cli') exit("This script is CLI only.\n");

require_once __DIR__ . '/../includes/db_mysql.php';

$apply = in_array('--apply', $argv, true);
$pdo = getDbConnection();

$cached = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE content_html IS NOT NULL")->fetchColumn();
$total  = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();

printf("%d of %d message(s) have cached HTML.\n", $cached, $total);

if (!$cached) exit(0);
if (!$apply) { echo "\nDry run: nothing changed. Run with --apply to clear the cache.\n"; exit(0); }

$cleared = $pdo->exec("UPDATE messages SET content_html = NULL WHERE content_html IS NOT NULL");
echo "\nCleared content_html on {$cleared} message(s); they re-render on next load.\n";
