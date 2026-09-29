<?php
/**
 * Remove knowledge-base chunks from sources that don't pass KnowledgeService::isTrustedSource().
 * Those chunks were stored before the trusted-source gate existed, and retrieval still
 * feeds them into the tutor's prompt.
 *
 *   php scripts/clean_knowledge_base.php          list what would go (changes nothing)
 *   php scripts/clean_knowledge_base.php --apply  delete it
 *
 * Only the 'general' topic: 'learning_strategies' has its own source rule
 * (LearningStrategiesService::isEducationalContent, which allows e.g. YouTube talks).
 */
if (php_sapi_name() !== 'cli') exit("This script is CLI only.\n");

require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/../api/knowledge.php';

$apply = in_array('--apply', $argv, true);
$pdo = getDbConnection();

$rows = $pdo->query("SELECT source_url, MAX(source_title) AS title, COUNT(*) AS chunks
                     FROM knowledge_base WHERE topic = 'general' GROUP BY source_url")->fetchAll(PDO::FETCH_ASSOC);
$drop = array_values(array_filter($rows, fn($r) => !KnowledgeService::isTrustedSource($r['source_url'])));

printf("%d source(s) in the general knowledge base, %d untrusted (%d chunks):\n",
    count($rows), count($drop), array_sum(array_column($drop, 'chunks')));
foreach ($drop as $r) {
    printf("  - [%d] %s  %s\n", $r['chunks'], $r['source_url'], mb_substr((string)$r['title'], 0, 50));
}

if (!$drop) exit(0);
if (!$apply) { echo "\nDry run: nothing deleted. Run with --apply to delete these.\n"; exit(0); }

$del = $pdo->prepare("DELETE FROM knowledge_base WHERE topic = 'general' AND source_url = ?");
$deleted = 0;
foreach ($drop as $r) { $del->execute([$r['source_url']]); $deleted += $del->rowCount(); }
echo "\nDeleted {$deleted} chunk(s) from " . count($drop) . " untrusted source(s).\n";
