<?php
/**
 * Knowledge Base API Service
 * 
 * Provides functions for:
 * - Web search via SerpAPI
 * - Content extraction from URLs
 * - Embedding generation via Gemini
 * - Storage and retrieval of knowledge chunks
 */

require_once __DIR__ . '/../db_mysql.php';

class KnowledgeService {
    private $pdo;
    private $serpApiKey;
    private $geminiApiKey;
    
    public function __construct() {
        $this->pdo = getDbConnection();
        
        // Load API keys from config
        $configFiles = ['../config-sql.ini', '../config.ini'];
        $config = null;
        
        foreach ($configFiles as $configFile) {
            $path = __DIR__ . '/' . $configFile;
            if (file_exists($path)) {
                $config = parse_ini_file($path);
                if ($config !== false) break;
            }
        }
        
        $this->serpApiKey = $config['SERP_API_KEY'] ?? null;
        // Allow using QUIZ_API_KEY for embeddings if GEMINI_API_KEY is not set or if specifically requested (future)
        // For now, continue to use GEMINI_API_KEY as primary for knowledge base, but be aware of others
        $this->geminiApiKey = $config['GEMINI_API_KEY'] ?? ($config['QUIZ_API_KEY'] ?? null);
    }
    
    /**
     * Search the web using SerpAPI
     * 
     * @param string $query Search query
     * @param int $numResults Number of results to return
     * @return array Search results
     */
    public function searchWeb($query, $numResults = 5) {
        if (!$this->serpApiKey) {
            error_log("KnowledgeService: SERP_API_KEY not configured");
            return [];
        }
        
        $params = [
            'q' => $query,
            'api_key' => $this->serpApiKey,
            'num' => $numResults,
            'engine' => 'google'
        ];
        
        $url = 'https://serpapi.com/search?' . http_build_query($params);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("SerpAPI error: HTTP $httpCode");
            return [];
        }
        
        $data = json_decode($response, true);
        
        // Extract organic results
        $results = [];
        if (isset($data['organic_results'])) {
            foreach ($data['organic_results'] as $result) {
                $results[] = [
                    'title' => $result['title'] ?? '',
                    'url' => $result['link'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                    'type' => $this->detectSourceType($result['link'] ?? '')
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Detect source type from URL
     */
    private function detectSourceType($url) {
        if (preg_match('/arxiv\.org/i', $url)) return 'paper';
        if (preg_match('/\.pdf$/i', $url)) return 'pdf';
        if (preg_match('/books\.google|amazon\.com.*\/dp\//i', $url)) return 'book';
        return 'webpage';
    }
    
    /**
     * Extract content from a URL
     * 
     * @param string $url The URL to extract content from
     * @return string|null Extracted text content
     */
    public function extractContent($url) {
        // Skip PDFs for now (would need PDF parser)
        if (preg_match('/\.pdf$/i', $url)) {
            error_log("KnowledgeService: PDF extraction not implemented yet");
            return null;
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml']
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$html) {
            error_log("Content extraction failed for $url: HTTP $httpCode");
            return null;
        }
        
        // Parse HTML and extract text
        return $this->parseHtmlToText($html);
    }
    
    /**
     * Parse HTML to plain text, extracting main content
     */
    private function parseHtmlToText($html) {
        // Ensure valid UTF-8 encoding
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'auto');
        }
        // Remove invalid UTF-8 sequences
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? $html;
        
        // Remove script and style elements
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $html) ?? $html;
        $html = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $html) ?? $html;
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $html) ?? $html;
        
        // Convert to text
        $text = strip_tags($html);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);
        
        // Limit to reasonable size (first 10000 chars)
        if (strlen($text) > 10000) {
            $text = substr($text, 0, 10000);
        }
        
        return $text;
    }
    
    /**
     * Split content into chunks for embedding
     * 
     * @param string $text Full text content
     * @param int $chunkSize Target chunk size in characters
     * @param int $overlap Overlap between chunks
     * @return array Array of text chunks
     */
    public function chunkContent($text, $chunkSize = 1500, $overlap = 200) {
        // Ensure text is a valid string
        if ($text === null || !is_string($text)) {
            error_log("KnowledgeService: chunkContent received non-string input: " . gettype($text));
            return [];
        }
        
        // Ensure valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        $chunks = [];
        $start = 0;
        $textLen = strlen($text);
        
        if ($textLen === 0) {
            return [];
        }
        
        // Ensure overlap is less than chunkSize
        $overlap = min($overlap, $chunkSize - 1);
        
        while ($start < $textLen) {
            $end = min($start + $chunkSize, $textLen);
            
            // Try to break at sentence boundary
            if ($end < $textLen) {
                $substring = substr($text, $start, $chunkSize);
                if ($substring !== false) {
                    $lastPeriod = strrpos($substring, '. ');
                    if ($lastPeriod !== false && $lastPeriod > $chunkSize * 0.5) {
                        $end = $start + $lastPeriod + 1;
                    }
                }
            }
            
            $chunk = substr($text, $start, $end - $start);
            if ($chunk !== false) {
                $chunk = trim($chunk);
                if (strlen($chunk) > 50) { // Only keep meaningful chunks
                    $chunks[] = $chunk;
                }
            }
            
            // Move start forward, ensuring progress
            $newStart = $end - $overlap;
            if ($newStart <= $start) {
                // Ensure we always move forward by at least 1 character
                $start = $end;
            } else {
                $start = $newStart;
            }
            
            // Safety: break if we've reached the end
            if ($start >= $textLen) break;
        }
        
        return $chunks;
    }
    
    /**
     * Generate embedding for text using Gemini API
     * 
     * @param string $text Text to embed
     * @return array|null Embedding vector
     */
    public function generateEmbedding($text) {
        if (!$this->geminiApiKey) {
            error_log("KnowledgeService: GEMINI_API_KEY not configured");
            return null;
        }
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key=" . $this->geminiApiKey;
        
        $payload = json_encode([
            'model' => 'models/text-embedding-004',
            'content' => [
                'parts' => [['text' => $text]]
            ]
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Embedding API error: HTTP $httpCode - " . substr($response, 0, 200));
            return null;
        }
        
        $data = json_decode($response, true);
        return $data['embedding']['values'] ?? null;
    }
    
    /**
     * Store knowledge chunk with embedding
     * 
     * @param string $url Source URL
     * @param string $title Source title
     * @param string $type Source type
     * @param string $chunk Text chunk
     * @param int $chunkIndex Index of chunk
     * @param array $embedding Embedding vector
     * @return bool Success
     */
    public function storeKnowledge($url, $title, $type, $chunk, $chunkIndex, $embedding = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO knowledge_base (source_url, source_title, source_type, content_chunk, chunk_index, embedding_json)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $embeddingJson = $embedding ? json_encode($embedding) : null;
            
            return $stmt->execute([$url, $title, $type, $chunk, $chunkIndex, $embeddingJson]);
        } catch (PDOException $e) {
            error_log("Failed to store knowledge: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if URL is already in knowledge base
     */
    public function urlExists($url) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM knowledge_base WHERE source_url = ?");
        $stmt->execute([$url]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Retrieve relevant knowledge chunks using cosine similarity
     * 
     * @param string $query Query text
     * @param int $limit Max results
     * @return array Relevant chunks
     */
    public function retrieveRelevant($query, $limit = 5) {
        // Generate query embedding
        $queryEmbedding = $this->generateEmbedding($query);
        if (!$queryEmbedding) {
            // Fallback to keyword search
            return $this->keywordSearch($query, $limit);
        }
        
        // Get all chunks with embeddings (in production, use a vector DB)
        $stmt = $this->pdo->query("SELECT id, source_title, content_chunk, embedding_json FROM knowledge_base WHERE embedding_json IS NOT NULL LIMIT 1000");
        $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate similarities
        $scored = [];
        foreach ($chunks as $chunk) {
            $embedding = json_decode($chunk['embedding_json'], true);
            if ($embedding) {
                $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);
                $scored[] = [
                    'id' => $chunk['id'],
                    'title' => $chunk['source_title'],
                    'content' => $chunk['content_chunk'],
                    'similarity' => $similarity
                ];
            }
        }
        
        // Sort by similarity
        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        
        // Return top results with similarity > 0.5
        $results = [];
        foreach (array_slice($scored, 0, $limit) as $item) {
            if ($item['similarity'] > 0.5) {
                $results[] = $item;
            }
        }
        
        return $results;
    }
    
    /**
     * Fallback keyword search
     */
    private function keywordSearch($query, $limit) {
        $words = explode(' ', $query);
        $words = array_filter($words, fn($w) => strlen($w) > 3);
        
        if (empty($words)) return [];
        
        $conditions = [];
        $params = [];
        foreach ($words as $word) {
            $conditions[] = "content_chunk LIKE ?";
            $params[] = "%$word%";
        }
        
        $sql = "SELECT id, source_title, content_chunk FROM knowledge_base WHERE " . implode(' OR ', $conditions) . " LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity($a, $b) {
        if (count($a) !== count($b)) return 0;
        
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        
        $normA = sqrt($normA);
        $normB = sqrt($normB);
        
        if ($normA == 0 || $normB == 0) return 0;
        
        return $dotProduct / ($normA * $normB);
    }
    
    /**
     * Process a resource mention: search, extract, and store
     * 
     * @param string $resourceName Name of the book/article/paper
     * @param string $author Optional author name
     * @return bool Success
     */
    public function processResourceMention($resourceName, $author = null) {
        $query = $resourceName;
        if ($author) {
            $query .= " by $author";
        }
        $query .= " summary content";
        
        error_log("KnowledgeService: Processing resource - $query");
        
        // Search for the resource
        $results = $this->searchWeb($query, 3);
        
        if (empty($results)) {
            error_log("KnowledgeService: No search results for $query");
            return false;
        }
        
        $processed = 0;
        foreach ($results as $result) {
            // Skip if already in knowledge base
            if ($this->urlExists($result['url'])) {
                continue;
            }
            
            // Extract content
            $content = $this->extractContent($result['url']);
            if (!$content || strlen($content) < 100) {
                continue;
            }
            
            // Chunk content
            $chunks = $this->chunkContent($content);
            
            // Store each chunk with embedding
            foreach ($chunks as $index => $chunk) {
                $embedding = $this->generateEmbedding($chunk);
                $this->storeKnowledge(
                    $result['url'],
                    $result['title'],
                    $result['type'],
                    $chunk,
                    $index,
                    $embedding
                );
                $processed++;
                
                // Rate limit embedding calls
                usleep(100000); // 100ms delay
            }
        }
        
        error_log("KnowledgeService: Stored $processed chunks for $resourceName");
        return $processed > 0;
    }
}

// Helper function for use in other files
function getKnowledgeService() {
    static $instance = null;
    if ($instance === null) {
        $instance = new KnowledgeService();
    }
    return $instance;
}
?>
