<?php
/**
 * Learning Strategies Service
 * 
 * Provides evidence-based learning strategies from:
 * 1. Embedded JSON files (core strategies)
 * 2. External sources (YouTube, blogs) via RAG
 * 
 * Integrates Justin Sung's methodologies and cognitive science research.
 */

require_once __DIR__ . '/knowledge.php';

class LearningStrategiesService {
    private $strategies = [];
    private $knowledgeService;
    private $strategiesPath;
    
    // Known educational YouTube channels and blogs for learning strategies
    private $trustedSources = [
        'youtube_channels' => [
            'Justin Sung' => 'UCR4OjY4RVtexAWrNzhdL0ew', // Justin Sung's channel ID
            'Thomas Frank' => 'UCG-KntY7aVnIGXYEBQvmBAQ',
            'Ali Abdaal' => 'UCoOae5nYA7VqaXzerajD0lg',
        ],
        'blogs' => [
            'https://www.learningscientists.org/blog',
            'https://bjorklab.psych.ucla.edu/',
        ],
        'search_terms' => [
            'Justin Sung learning strategies',
            'evidence-based study techniques',
            'active recall spaced repetition',
            'higher order learning techniques',
            'cognitive science learning',
        ]
    ];
    
    public function __construct() {
        $this->strategiesPath = __DIR__ . '/../data/strategies/';
        $this->knowledgeService = getKnowledgeService();
        $this->loadStrategies();
    }
    
    /**
     * Load strategies from embedded JSON files
     */
    private function loadStrategies() {
        $files = glob($this->strategiesPath . '*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if ($data && isset($data['strategies'])) {
                foreach ($data['strategies'] as $strategy) {
                    $this->strategies[$strategy['id']] = $strategy;
                }
            }
        }
        
        error_log("LearningStrategiesService: Loaded " . count($this->strategies) . " strategies");
    }
    
    /**
     * Get all strategies
     */
    public function getAllStrategies() {
        return $this->strategies;
    }
    
    /**
     * Get a specific strategy by ID
     */
    public function getStrategy($id) {
        return $this->strategies[$id] ?? null;
    }
    
    /**
     * Get strategies by category
     */
    public function getStrategiesByCategory($category) {
        return array_filter($this->strategies, function($s) use ($category) {
            return ($s['category'] ?? '') === $category;
        });
    }
    
    /**
     * Get relevant strategies for a given learning context
     * 
     * @param string $context Description of what the learner is trying to do
     * @return array Relevant strategies with tutor prompts
     */
    public function getRelevantStrategies($context) {
        $relevant = [];
        $contextLower = strtolower($context);
        
        // Keywords to strategy mapping
        $keywords = [
            'memorize|remember|recall|forget' => ['active-recall', 'spaced-repetition'],
            'understand|concept|why|how' => ['higher-order-learning', 'elaborative-interrogation'],
            'confused|stuck|don\'t get' => ['concrete-examples', 'self-explanation'],
            'exam|test|quiz' => ['active-recall', 'interleaving', 'spaced-repetition'],
            'practice|problem|exercise' => ['interleaving', 'active-recall'],
            'visual|diagram|picture' => ['dual-coding'],
        ];
        
        foreach ($keywords as $pattern => $strategyIds) {
            if (preg_match("/$pattern/i", $contextLower)) {
                foreach ($strategyIds as $id) {
                    if (isset($this->strategies[$id]) && !isset($relevant[$id])) {
                        $relevant[$id] = $this->strategies[$id];
                    }
                }
            }
        }
        
        // Always include core strategies if nothing specific matched
        if (empty($relevant)) {
            $relevant['higher-order-learning'] = $this->strategies['higher-order-learning'] ?? null;
            $relevant['encoding-strategies'] = $this->strategies['encoding-strategies'] ?? null;
        }
        
        return array_filter($relevant);
    }
    
    /**
     * Get tutor prompts for a specific strategy
     */
    public function getTutorPrompts($strategyId) {
        $strategy = $this->getStrategy($strategyId);
        return $strategy['tutor_prompts'] ?? [];
    }
    
    /**
     * Generate a strategy-informed system prompt addition
     * 
     * @param string $learnerContext Context about what the learner is doing
     * @return string Additional system prompt text
     */
    public function generateStrategyContext($learnerContext = '') {
        $relevant = $this->getRelevantStrategies($learnerContext);
        
        if (empty($relevant)) {
            // Return core teaching philosophy
            return $this->getCorePhilosophy();
        }
        
        $prompt = "\n\n## Applied Learning Strategies\n";
        $prompt .= "Based on the learner's context, apply these evidence-based strategies:\n\n";
        
        foreach ($relevant as $strategy) {
            $prompt .= "### {$strategy['name']}\n";
            $prompt .= "{$strategy['description']}\n";
            
            if (!empty($strategy['tutor_prompts'])) {
                $prompt .= "**Apply by:**\n";
                foreach (array_slice($strategy['tutor_prompts'], 0, 2) as $p) {
                    $prompt .= "- $p\n";
                }
            }
            $prompt .= "\n";
        }
        
        $prompt .= "**Remember:** Apply these naturally. Don't announce that you're using a strategy.\n";
        
        return $prompt;
    }
    
    /**
     * Get core teaching philosophy
     */
    private function getCorePhilosophy() {
        return <<<EOT

## Core Teaching Philosophy (Justin Sung Methodology)

1. **Prioritize Understanding Over Memorization**
   - Always ask "why" and "how", not just "what"
   - Seek the underlying principles, not surface facts
   - Challenge students to explain in their own words

2. **Active Learning > Passive Review**
   - Don't just explain - make them think
   - Ask questions before giving answers
   - Create productive struggle

3. **Build Connections**
   - Link new concepts to prior knowledge
   - Show relationships between ideas
   - Help build mental models

4. **Metacognition**
   - Help them recognize what they truly understand vs. what feels familiar
   - Encourage self-testing and reflection
   - Build awareness of their own learning process

EOT;
    }
    
    /**
     * Fetch additional learning content from external sources
     * Uses RAG knowledge service for web search and content extraction
     * 
     * @param string $topic Specific topic to search for
     * @return bool Success
     */
    public function fetchExternalContent($topic = 'effective learning strategies') {
        $searchQueries = [
            "Justin Sung $topic",
            "evidence-based $topic",
            "cognitive science $topic research",
        ];
        
        $processed = 0;
        foreach ($searchQueries as $query) {
            try {
                $results = $this->knowledgeService->searchWeb($query, 3);
                
                foreach ($results as $result) {
                    // Filter for educational content
                    if ($this->isEducationalContent($result['url'])) {
                        if (!$this->knowledgeService->urlExists($result['url'])) {
                            $content = $this->knowledgeService->extractContent($result['url']);
                            if ($content && strlen($content) > 200) {
                                $chunks = $this->knowledgeService->chunkContent($content);
                                
                                foreach (array_slice($chunks, 0, 3) as $index => $chunk) {
                                    $embedding = $this->knowledgeService->generateEmbedding($chunk);
                                    $this->knowledgeService->storeKnowledge(
                                        $result['url'],
                                        $result['title'],
                                        'webpage',
                                        $chunk,
                                        $index,
                                        $embedding
                                    );
                                    $processed++;
                                    usleep(100000); // Rate limit
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("External content fetch error: " . $e->getMessage());
            }
        }
        
        error_log("LearningStrategiesService: Fetched $processed chunks of external content for '$topic'");
        return $processed > 0;
    }
    
    /**
     * Check if URL is likely educational content
     */
    private function isEducationalContent($url) {
        $educationalPatterns = [
            'youtube\.com',
            'learningscientists\.org',
            'bjorklab',
            'edutopia',
            'coursera',
            'medium\.com',
            'substack',
            'notion\.site',
            'edu$',
        ];
        
        foreach ($educationalPatterns as $pattern) {
            if (preg_match("/$pattern/i", $url)) {
                return true;
            }
        }
        
        return true; // Allow most content for now
    }
    
    /**
     * Get a random tip from strategies for quick context enrichment
     */
    public function getRandomTip() {
        $tips = [];
        foreach ($this->strategies as $strategy) {
            if (!empty($strategy['tutor_prompts'])) {
                foreach ($strategy['tutor_prompts'] as $prompt) {
                    $tips[] = [
                        'strategy' => $strategy['name'],
                        'tip' => $prompt
                    ];
                }
            }
        }
        
        if (empty($tips)) return null;
        return $tips[array_rand($tips)];
    }
}

// Singleton accessor
function getLearningStrategiesService() {
    static $instance = null;
    if ($instance === null) {
        $instance = new LearningStrategiesService();
    }
    return $instance;
}
?>
