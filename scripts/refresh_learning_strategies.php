<?php
/**
 * Refresh the learning-strategies knowledge base (Justin Sung + evidence-based
 * learning science) from the web. Run every two weeks by cPanel cron:
 *   0 3 1,15 * *   (03:00 on the 1st and 15th)
 *
 *   php scripts/refresh_learning_strategies.php            fetch + store new sources
 *   php scripts/refresh_learning_strategies.php --dry-run  search only: show what would be kept
 *
 * Each run covers the next TOPICS_PER_RUN topics in the rotation (position kept in
 * logs/learning_strategies_rotation.txt), so the whole list is refreshed over time
 * without spending much SerpAPI quota: each topic = 3 searches
 * (LearningStrategiesService::fetchExternalContent). Already-stored URLs are skipped,
 * and only sources passing isEducationalContent() are stored.
 */
if (php_sapi_name() !== 'cli') exit("This script is CLI only.\n");

require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/../api/learning_strategies.php';

const TOPICS = [
    'effective learning strategies',
    'active recall',
    'spaced repetition',
    'interleaving practice',
    'mind mapping and non-linear notes',
    'deep processing and higher-order learning',
    'exam preparation techniques',
    'overcoming procrastination while studying',
];
const TOPICS_PER_RUN = 2;   // 2 topics × 3 searches = 6 SerpAPI searches per run

$dryRun = in_array('--dry-run', $argv, true);
$stateFile = __DIR__ . '/../logs/learning_strategies_rotation.txt';
$start = (int)@file_get_contents($stateFile) % count(TOPICS);
$topics = [];
for ($i = 0; $i < TOPICS_PER_RUN; $i++) $topics[] = TOPICS[($start + $i) % count(TOPICS)];

$service = getLearningStrategiesService();
$knowledge = getKnowledgeService();
$pdo = getDbConnection();
$count = fn() => (int)$pdo->query("SELECT COUNT(*) FROM knowledge_base WHERE topic = 'learning_strategies'")->fetchColumn();
$before = $count();

foreach ($topics as $topic) {
    echo "Topic: {$topic}\n";
    if ($dryRun) {
        foreach (["Justin Sung $topic", "evidence-based $topic", "cognitive science $topic research"] as $q) {
            foreach ($knowledge->searchWeb($q, 3, 30) as $r) {
                $verdict = !$service->isEducationalContent($r['url']) ? 'skip (not educational)'
                         : ($knowledge->urlExists($r['url']) ? 'skip (already stored)' : 'KEEP');
                printf("  %-24s %s\n", $verdict, $r['url']);
            }
        }
    } else {
        $service->fetchExternalContent($topic);
    }
}

if (!$dryRun) {
    @mkdir(dirname($stateFile), 0755, true);
    file_put_contents($stateFile, (string)(($start + TOPICS_PER_RUN) % count(TOPICS)));
    printf("Done: %d new chunk(s) stored (learning_strategies now %d). Next run starts at \"%s\".\n",
        $count() - $before, $count(), TOPICS[($start + TOPICS_PER_RUN) % count(TOPICS)]);
} else {
    echo "Dry run: nothing stored, rotation not advanced.\n";
}
