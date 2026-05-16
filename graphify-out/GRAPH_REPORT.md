# Graph Report - c:\xampp\htdocs\TutorMind  (2026-05-05)

## Corpus Check
- Large corpus: 1429 files · ~1,099,643 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder, or use --no-semantic to run AST-only.

## Summary
- 415 nodes · 598 edges · 73 communities (59 shown, 14 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 13 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]

## God Nodes (most connected - your core abstractions)
1. `OnboardingWizard` - 38 edges
2. `SettingsManager` - 31 edges
3. `TutorMindChat` - 28 edges
4. `OnboardingWizard` - 24 edges
5. `SessionContextManager` - 21 edges
6. `QuickStartManager` - 18 edges
7. `KnowledgeService` - 15 edges
8. `LearningStrategiesService` - 13 edges
9. `PomodoroManager` - 11 edges
10. `QuizManager` - 11 edges

## Surprising Connections (you probably didn't know these)
- `migrate_add_performance_indexes()` --calls--> `getDbConnection()`  [INFERRED]
  migrations/008_add_performance_indexes.php → includes/db_mysql.php
- `migrate_add_message_cache()` --calls--> `getDbConnection()`  [INFERRED]
  migrations/009_add_message_cache.php → includes/db_mysql.php
- `isRateLimited()` --calls--> `getDbConnection()`  [INFERRED]
  includes/rate_limiter.php → includes/db_mysql.php
- `recordFailedAttempt()` --calls--> `getDbConnection()`  [INFERRED]
  includes/rate_limiter.php → includes/db_mysql.php
- `clearLoginAttempts()` --calls--> `getDbConnection()`  [INFERRED]
  includes/rate_limiter.php → includes/db_mysql.php

## Communities (73 total, 14 thin omitted)

### Community 1 - "Community 1"
Cohesion: 0.1
Nodes (4): SettingsManager, update_css(), update_js(), update_php()

### Community 2 - "Community 2"
Cohesion: 0.11
Nodes (27): addCopyButtonsToCodeBlocks(), addMessage(), base64ToBlob(), browserSpeak(), createSelectionPopup(), deleteConversation(), fallbackCopyText(), finalizeMessage() (+19 more)

### Community 6 - "Community 6"
Cohesion: 0.15
Nodes (13): addCourse(), calculateCGPAFrontend(), calculateClientSide(), closeModal(), createCourseRow(), displayAdvancedPredictionResults(), displayResults(), getClassification() (+5 more)

### Community 10 - "Community 10"
Cohesion: 0.19
Nodes (7): getDbConnection(), cleanupOldAttempts(), clearLoginAttempts(), isRateLimited(), recordFailedAttempt(), migrate_add_performance_indexes(), migrate_add_message_cache()

### Community 14 - "Community 14"
Cohesion: 0.46
Nodes (7): compactDocumentText(), extractTableOfContents(), ocrImageBasedPdf(), ocrWithGoogleCloudVision(), ocrWithOcrSpace(), ocrWithTesseract(), prepareFileParts()

### Community 15 - "Community 15"
Cohesion: 0.43
Nodes (6): getParticleColor(), initTheme(), updateLineColors(), updateLineMaterial(), updateParticleMaterial(), updateThemeVisuals()

### Community 16 - "Community 16"
Cohesion: 0.52
Nodes (5): callGemini(), callGroqJson(), handleGenerate(), handleGrade(), updateQuizResult()

### Community 19 - "Community 19"
Cohesion: 0.6
Nodes (4): generateCSRFToken(), getCSRFInput(), requireCSRFToken(), validateCSRFToken()

## Knowledge Gaps
- **14 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `loadChatHistory()` connect `Community 2` to `Community 1`?**
  _High betweenness centrality (0.036) - this node is a cross-community bridge._
- **Why does `loadConversation()` connect `Community 2` to `Community 7`?**
  _High betweenness centrality (0.022) - this node is a cross-community bridge._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.11 - nodes in this community are weakly interconnected._
- **Should `Community 3` be split into smaller, more focused modules?**
  _Cohesion score 0.12 - nodes in this community are weakly interconnected._