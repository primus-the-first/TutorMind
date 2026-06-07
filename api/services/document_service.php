<?php
/**
 * Document & OCR Service
 * Handles file parsing (PDF, DOCX, PPTX, images) and OCR fallback chain.
 * Optimized for token management via structure extraction and compaction.
 */

/**
 * Extracts the Table of Contents or structural map from a document using AI.
 */
function extractTableOfContents($filePath, $extension, $apiKey) {
    $structureText = "";

    try {
        if ($extension === 'pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            // Textbooks usually have TOC in the first 15 pages
            $sampleLimit = min(15, $pageCount);
            for ($i = 0; $i < $sampleLimit; $i++) {
                $structureText .= $pages[$i]->getText() . "\n";
            }
        } elseif ($extension === 'docx') {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $structureText .= $textElement->getText() . ' ';
                                if (strlen($structureText) > 15000) break 3;
                            }
                        }
                    }
                }
            }
        }

        if (empty(trim($structureText))) return null;

        $prompt = "Analyze the following text from the beginning of a document and extract the Table of Contents or a detailed structural map. If no formal TOC exists, create a logical outline based on the headings. Return ONLY the structural map in Markdown format.\n\nTEXT:\n" . substr($structureText, 0, 20000);

        $payload = json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]]
        ]);

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        }
    } catch (Exception $e) {
        error_log("TOC Extraction Error: " . $e->getMessage());
    }

    return null;
}

/**
 * Compacts long text into a denser, information-rich summary for AI context.
 */
function compactDocumentText($text, $apiKey) {
    if (strlen($text) < 5000) return $text;

    try {
        $prompt = "Compress the following document content into a high-density, information-rich summary for an AI tutor. Retain all technical terms, definitions, key formulas, and core concepts. Remove fluff, repetitive examples, and filler. Structure it logically with clear headings.\n\nCONTENT:\n" . substr($text, 0, 30000);

        $payload = json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]]
        ]);

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $compacted = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($compacted) return "[COMPACTED CONTENT]\n" . $compacted;
        }
    } catch (Exception $e) {
        error_log("Compaction Error: " . $e->getMessage());
    }

    return substr($text, 0, 15000) . "... [Truncated due to length]";
}

/**
 * Fetch a YouTube transcript using only PHP/cURL — no Python or exec() needed.
 * Works by extracting the caption track URL embedded in the YouTube page HTML.
 * Returns plain transcript text, or empty string if captions are unavailable.
 */
function fetchYoutubeTranscript(string $videoId): string
{
    $pageUrl = 'https://www.youtube.com/watch?v=' . urlencode($videoId);

    $ch = curl_init($pageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$html || $httpCode !== 200) {
        error_log("YouTube transcript: failed to fetch page (HTTP {$httpCode})");
        return '';
    }

    // YouTube embeds all player data in a JS variable on the page
    if (!preg_match('/ytInitialPlayerResponse\s*=\s*(\{.+?\});(?:\s*(?:var|const|let)\s|\s*<\/script>)/s', $html, $m)) {
        error_log("YouTube transcript: ytInitialPlayerResponse not found in page");
        return '';
    }

    $player = json_decode($m[1], true);
    if (!is_array($player)) {
        error_log("YouTube transcript: could not parse ytInitialPlayerResponse JSON");
        return '';
    }

    // Navigate to caption tracks
    $tracks = $player['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? [];
    if (empty($tracks)) {
        error_log("YouTube transcript: no caption tracks found for video {$videoId}");
        return '';
    }

    // Prefer English; fall back to first available track
    $trackUrl = null;
    foreach ($tracks as $track) {
        $lang = strtolower($track['languageCode'] ?? '');
        if (str_starts_with($lang, 'en')) {
            $trackUrl = $track['baseUrl'] ?? null;
            break;
        }
    }
    if (!$trackUrl) {
        $trackUrl = $tracks[0]['baseUrl'] ?? null;
    }
    if (!$trackUrl) {
        return '';
    }

    // Fetch the caption XML
    $ch = curl_init($trackUrl . '&fmt=json3');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $captionJson = curl_exec($ch);
    curl_close($ch);

    // Try JSON3 format first (cleaner)
    $captionData = json_decode($captionJson, true);
    if (isset($captionData['events'])) {
        $lines = [];
        foreach ($captionData['events'] as $event) {
            if (empty($event['segs'])) continue;
            $seg = implode('', array_column($event['segs'], 'utf8'));
            $seg = trim($seg);
            if ($seg !== '') $lines[] = $seg;
        }
        return implode(' ', $lines);
    }

    // Fallback: fetch as XML
    $ch = curl_init($trackUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $captionXml = curl_exec($ch);
    curl_close($ch);

    if (!$captionXml) return '';

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($captionXml);
    if (!$xml) return '';

    $lines = [];
    foreach ($xml->text as $node) {
        $line = html_entity_decode((string)$node, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $line = trim(preg_replace('/\s+/', ' ', $line));
        if ($line !== '') $lines[] = $line;
    }

    return implode(' ', $lines);
}

/**
 * Extract text from a file or URL using MarkItDown (Python).
 * Returns extracted text, or empty string on failure.
 */
function extractWithMarkItDown(string $source): string
{
    $pythonCandidates = [
        'C:\\Python314\\python.exe',
        'C:\\Python313\\python.exe',
        'C:\\Python312\\python.exe',
        'C:\\Python311\\python.exe',
        'C:\\Python310\\python.exe',
        'C:\\Python39\\python.exe',
        'python', 'python3', 'py',
    ];
    $python = null;
    foreach ($pythonCandidates as $candidate) {
        $quoted = escapeshellarg($candidate);
        exec("{$quoted} --version 2>&1", $out, $code);
        if ($code === 0) {
            $python = $quoted;
            break;
        }
        $out = [];
    }

    if (!$python) {
        error_log("MarkItDown: No Python interpreter found");
        return '';
    }

    $script = escapeshellarg(__DIR__ . '/markitdown_extract.py');
    $arg    = escapeshellarg($source);
    $cmd    = "{$python} -X utf8 {$script} {$arg} 2>&1";

    exec($cmd, $lines, $exitCode);
    $output = implode("\n", $lines);

    // Python may emit warnings before or after the JSON; extract just the JSON object
    if (!preg_match('/(\{.*\})/s', $output, $jsonMatch)) {
        error_log("MarkItDown: No JSON found in output — " . substr($output, 0, 300));
        return '';
    }

    $data = json_decode($jsonMatch[1], true);
    if (!is_array($data)) {
        error_log("MarkItDown: JSON decode failed — " . substr($jsonMatch[1], 0, 300));
        return '';
    }

    if (!($data['success'] ?? false)) {
        error_log("MarkItDown: " . ($data['error'] ?? 'unknown error'));
        return '';
    }

    return $data['text'] ?? '';
}

function ocrImageBasedPdf($pdfPath, $originalName) {
    $config = null;
    foreach ([__DIR__ . '/../../includes/config-sql.ini', __DIR__ . '/../../includes/config.ini'] as $configFile) {
        if (file_exists($configFile)) {
            $config = parse_ini_file($configFile);
            if ($config !== false) break;
        }
    }

    // 1. Try Google Cloud Vision API (primary)
    if (!empty($config['GOOGLE_CLOUD_VISION_API_KEY'])) {
        try {
            $text = ocrWithGoogleCloudVision($pdfPath, $config['GOOGLE_CLOUD_VISION_API_KEY']);
            if (!empty(trim($text))) {
                error_log("OCR [{$originalName}]: Success with Google Cloud Vision");
                return $text;
            }
        } catch (Exception $e) {
            error_log("Google Cloud Vision OCR failed: " . $e->getMessage());
        }
    }

    // 2. Try OCR.space API (fallback)
    if (!empty($config['OCR_SPACE_API_KEY'])) {
        try {
            $text = ocrWithOcrSpace($pdfPath, $config['OCR_SPACE_API_KEY']);
            if (!empty(trim($text))) {
                error_log("OCR [{$originalName}]: Success with OCR.space");
                return $text;
            }
        } catch (Exception $e) {
            error_log("OCR.space OCR failed: " . $e->getMessage());
        }
    }

    // 3. Try Tesseract (local fallback - last resort)
    try {
        $text = ocrWithTesseract($pdfPath);
        if (!empty(trim($text))) {
            error_log("OCR [{$originalName}]: Success with Tesseract (local)");
            return $text;
        }
    } catch (Exception $e) {
        error_log("Tesseract OCR failed: " . $e->getMessage());
    }

    if (empty($config['GOOGLE_CLOUD_VISION_API_KEY']) && empty($config['OCR_SPACE_API_KEY'])) {
        throw new Exception("This PDF appears to be image-based (scanned). To process it, add your OCR API keys to config.ini (GOOGLE_CLOUD_VISION_API_KEY or OCR_SPACE_API_KEY).");
    }

    throw new Exception("Could not extract text from '{$originalName}'. All OCR methods failed. The document may be blank or unreadable.");
}

function ocrWithGoogleCloudVision($pdfPath, $apiKey) {
    $fileData = base64_encode(file_get_contents($pdfPath));
    $fileSize = filesize($pdfPath);

    if ($fileSize > 20 * 1024 * 1024) {
        throw new Exception("PDF too large for Cloud Vision API (max 20MB)");
    }

    $url = "https://vision.googleapis.com/v1/files:annotate?key=" . urlencode($apiKey);

    $requestBody = [
        'requests' => [
            [
                'inputConfig' => [
                    'mimeType' => 'application/pdf',
                    'content' => $fileData
                ],
                'features' => [
                    ['type' => 'DOCUMENT_TEXT_DETECTION']
                ],
                'outputConfig' => [
                    'pageCount' => 5
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        throw new Exception($error['error']['message'] ?? "API returned HTTP {$httpCode}");
    }

    $result = json_decode($response, true);
    $fullText = '';

    if (isset($result['responses'])) {
        foreach ($result['responses'] as $pageResponse) {
            if (isset($pageResponse['fullTextAnnotation']['text'])) {
                $fullText .= $pageResponse['fullTextAnnotation']['text'] . "\n\n";
            }
        }
    }

    return trim($fullText);
}

function ocrWithOcrSpace($pdfPath, $apiKey) {
    $fileSize = filesize($pdfPath);

    if ($fileSize > 5 * 1024 * 1024) {
        throw new Exception("PDF too large for OCR.space API (max 5MB)");
    }

    $url = "https://api.ocr.space/parse/image";
    $cfile = new CURLFile($pdfPath, 'application/pdf', basename($pdfPath));

    $postData = [
        'apikey' => $apiKey,
        'file' => $cfile,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'filetype' => 'PDF',
        'detectOrientation' => 'true',
        'scale' => 'true',
        'OCREngine' => '2'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OCR.space API returned HTTP {$httpCode}");
    }

    $result = json_decode($response, true);

    if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing']) {
        throw new Exception($result['ErrorMessage'][0] ?? 'Unknown OCR.space error');
    }

    $fullText = '';
    if (isset($result['ParsedResults'])) {
        foreach ($result['ParsedResults'] as $page) {
            if (isset($page['ParsedText'])) {
                $fullText .= $page['ParsedText'] . "\n\n";
            }
        }
    }

    return trim($fullText);
}

function ocrWithTesseract($pdfPath) {
    $tesseractPath = '';
    $possiblePaths = [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        '/usr/bin/tesseract',
        '/usr/local/bin/tesseract',
        'tesseract'
    ];

    foreach ($possiblePaths as $path) {
        if ($path === 'tesseract' || $path === '/usr/bin/tesseract' || $path === '/usr/local/bin/tesseract') {
            exec("{$path} --version 2>&1", $output, $returnCode);
            if ($returnCode === 0) {
                $tesseractPath = $path;
                break;
            }
        } elseif (file_exists($path)) {
            $tesseractPath = $path;
            break;
        }
    }

    if (empty($tesseractPath)) {
        throw new Exception("Tesseract OCR not installed");
    }

    $gsPath = '';
    $gsPaths = [
        'C:\\Program Files\\gs\\gs10.04.0\\bin\\gswin64c.exe',
        'C:\\Program Files\\gs\\gs10.03.1\\bin\\gswin64c.exe',
        'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe',
        'C:\\Program Files (x86)\\gs\\gs10.04.0\\bin\\gswin32c.exe',
        '/usr/bin/gs',
        '/usr/local/bin/gs',
        'gswin64c', 'gswin32c', 'gs'
    ];

    foreach ($gsPaths as $path) {
        if (in_array($path, ['gswin64c', 'gswin32c', 'gs', '/usr/bin/gs', '/usr/local/bin/gs'])) {
            exec("{$path} --version 2>&1", $output, $returnCode);
            if ($returnCode === 0) {
                $gsPath = $path;
                break;
            }
        } elseif (file_exists($path)) {
            $gsPath = $path;
            break;
        }
    }

    if (empty($gsPath)) {
        throw new Exception("Ghostscript not installed (required for PDF to image conversion)");
    }

    $tempDir = sys_get_temp_dir() . '/tutormind_ocr_' . uniqid();
    if (!mkdir($tempDir, 0755, true)) {
        throw new Exception("Could not create temp directory");
    }

    try {
        $imagePrefix = $tempDir . '/page';
        $gsCmd = sprintf(
            '"%s" -dNOPAUSE -dBATCH -sDEVICE=png16m -r200 -sOutputFile="%s-%%03d.png" "%s" 2>&1',
            $gsPath, $imagePrefix, $pdfPath
        );

        exec($gsCmd, $gsOutput, $gsReturnCode);

        if ($gsReturnCode !== 0) {
            throw new Exception("Ghostscript failed: " . implode(" ", $gsOutput));
        }

        $images = glob($tempDir . '/page-*.png');
        if (empty($images)) {
            throw new Exception("No pages extracted from PDF");
        }

        sort($images);
        $fullText = '';
        $tesseract = new \thiagoalessio\TesseractOCR\TesseractOCR();

        foreach ($images as $index => $imagePath) {
            try {
                $tesseract->image($imagePath);
                if (strpos($tesseractPath, 'tesseract') === false || file_exists($tesseractPath)) {
                    $tesseract->executable($tesseractPath);
                }
                $pageText = $tesseract->run();

                if (!empty(trim($pageText))) {
                    $fullText .= "--- Page " . ($index + 1) . " ---\n" . $pageText . "\n\n";
                }
            } catch (Exception $e) {
                error_log("Tesseract page " . ($index + 1) . " error: " . $e->getMessage());
            }
            @unlink($imagePath);
        }

        return trim($fullText);

    } finally {
        @rmdir($tempDir);
    }
}

/**
 * Resize raw image bytes to fit within 800×800 and re-encode as JPEG.
 * Returns the compressed JPEG bytes, or null if GD can't load the image.
 */
function resizeImageData(string $raw): ?string
{
    if (!extension_loaded('gd')) return $raw; // no GD — pass through as-is

    $src = @imagecreatefromstring($raw);
    if (!$src) return null;

    $ow = imagesx($src);
    $oh = imagesy($src);
    $max = 600;

    if ($ow <= $max && $oh <= $max) {
        // Already small enough — just re-encode as JPEG to normalise format
        $nw = $ow;
        $nh = $oh;
    } else {
        $scale = min($max / $ow, $max / $oh);
        $nw    = (int) ($ow * $scale);
        $nh    = (int) ($oh * $scale);
    }

    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, 75);
    $jpeg = ob_get_clean();
    imagedestroy($dst);

    return $jpeg ?: null;
}

/**
 * Pull all raster images out of a PPTX (which is a ZIP) from its ppt/media/ folder.
 * Returns an array of ['mime' => ..., 'data' => base64] entries, one per image.
 */
function extractImagesFromPptx(string $filePath): array
{
    if (!class_exists('ZipArchive')) return [];

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return [];

    $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp'];
    $images = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!preg_match('#^ppt/media/.+\.(' . implode('|', array_keys($mimeMap)) . ')$#i', $name, $m)) continue;
        $raw = $zip->getFromIndex($i);
        if ($raw === false || strlen($raw) < 100) continue;

        // Resize to max 800px to keep token usage low
        $resized = resizeImageData($raw);
        if ($resized === null) continue;

        $images[] = ['mime' => 'image/jpeg', 'data' => base64_encode($resized)];
    }

    $zip->close();
    return $images;
}

/**
 * Send slide images to Gemini Vision and return the extracted text.
 */
function ocrPptxWithGemini(array $images, string $apiKey): string
{
    if (empty($images) || !$apiKey) return '';

    $parts = [];
    foreach (array_slice($images, 0, 6) as $img) {
        $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => $img['data']]];
    }
    $parts[] = ['text' => 'These are slides from a presentation. Extract ALL text visible in each slide, organized by slide. Preserve headings, bullet points, and any labels or captions. Return only the extracted text content.'];

    $payload  = json_encode(['contents' => [['parts' => $parts]]]);
    $apiUrl   = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("ocrPptxWithGemini: HTTP {$httpCode} — " . substr($response, 0, 300));
        return '';
    }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) {
        $finishReason = $data['candidates'][0]['finishReason'] ?? 'unknown';
        error_log("ocrPptxWithGemini: empty text, finishReason={$finishReason} — " . substr($response, 0, 500));
    }
    return $text;
}

function prepareFileParts($file, $user_question)
{
    $filePath = $file['tmp_name'];
    if (!file_exists($filePath)) {
        throw new Exception("File upload failed: Temporary file not found.");
    }

    $fileType = mime_content_type($filePath);
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed_types = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
    ];

    if (!in_array($extension, array_keys($allowed_types))) {
        throw new Exception("Unsupported file type: {$extension}.");
    }

    // DOCX and PPTX are ZIP-based; mime_content_type() returns zip/octet-stream/ooxml depending on OS — trust the extension
    if (!in_array($extension, ['docx', 'pptx']) && !in_array($fileType, $allowed_types)) {
        throw new Exception("File content does not match its extension ({$extension} vs {$fileType}).");
    }

    // Handle images
    if (strpos($fileType, 'image/') === 0) {
        if (!extension_loaded('gd')) {
            throw new Exception("The 'gd' PHP extension is required to process images but it is not enabled. Please enable it in your php.ini file.");
        }

        $srcImage = null;
        switch ($fileType) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($filePath);
                break;
            case 'image/bmp':
                $srcImage = imagecreatefrombmp($filePath);
                break;
            case 'image/webp':
                $srcImage = imagecreatefromwebp($filePath);
                break;
        }

        if (!$srcImage) {
            throw new Exception("Could not process the image file '{$originalName}'.");
        }

        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        $maxWidth = 1024;
        $maxHeight = 1024;

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int) ($origWidth * $ratio);
        $newHeight = (int) ($origHeight * $ratio);

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($fileType === 'image/png' || $fileType === 'image/gif') {
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $white);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($dstImage, null, 75);
            $fileData = ob_get_clean();
            $fileType = 'image/webp';
        } else {
            imagejpeg($dstImage, null, 75);
            $fileData = ob_get_clean();
            $fileType = 'image/jpeg';
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        $maxSizeBytes = 3 * 1024 * 1024;
        if (strlen($fileData) > $maxSizeBytes) {
            throw new Exception("Image file is too large even after compression. Please use a smaller image.");
        }

        $base64Data = base64_encode($fileData);

        return [
            'inline_data' => ['mime_type' => $fileType, 'data' => $base64Data]
        ];
    }

    // Load API key early — needed for PPTX image fallback and later compaction
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null;
    if (!$apiKey) {
        foreach ([__DIR__ . '/../../includes/config-sql.ini', __DIR__ . '/../../includes/config.ini'] as $configFile) {
            if (file_exists($configFile)) {
                $cfg = parse_ini_file($configFile);
                if (isset($cfg['GEMINI_API_KEY'])) {
                    $apiKey = $cfg['GEMINI_API_KEY'];
                    break;
                }
            }
        }
    }

    $text = '';
    switch ($extension) {
        case 'txt':
            $text = file_get_contents($filePath);
            break;
        case 'pdf':
            $text = extractWithMarkItDown($filePath);
            error_log("MarkItDown PDF [{$originalName}]: " . strlen($text) . " chars extracted");
            if (empty(trim($text))) {
                $text = ocrImageBasedPdf($filePath, $originalName);
            }
            break;
        case 'docx':
            $text = extractWithMarkItDown($filePath);
            error_log("MarkItDown docx [{$originalName}]: " . strlen($text) . " chars extracted");
            break;
        case 'pptx':
            $pptxSizeBytes = filesize($filePath);
            if ($pptxSizeBytes > 50 * 1024 * 1024) {
                $mb = round($pptxSizeBytes / 1024 / 1024, 1);
                throw new Exception("The presentation '{$originalName}' is {$mb} MB, which is too large to process (limit: 50 MB). Please compress the images in the file or export a lower-resolution version before uploading.");
            }
            $text = extractWithMarkItDown($filePath);
            error_log("MarkItDown pptx [{$originalName}]: " . strlen($text) . " chars extracted");
            // Strip markdown image refs and HTML comments to detect truly text-free decks
            $strippedText = trim(preg_replace(['/<!--.*?-->/s', '/!\[.*?\]\(.*?\)/'], '', $text));
            if (empty($strippedText)) {
                // Image-only deck — pass slide images directly into the Gemini chat request
                $slideImages = extractImagesFromPptx($filePath);
                error_log("MarkItDown pptx [{$originalName}]: image-only, returning " . count($slideImages) . " slide images as inline_data");
                if (!empty($slideImages)) {
                    $parts = [['text' => "The following images are slides from the uploaded presentation '{$originalName}'. Analyse their content to answer the user's question."]];
                    foreach (array_slice($slideImages, 0, 6) as $img) {
                        $parts[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $img['data']]];
                    }
                    return $parts;
                }
                // No raster images found (slides may use vector graphics) — surface as a soft error
                return [['text' => "System note: The presentation '{$originalName}' could not be read. It appears to contain only vector graphics or no extractable content. Ask the user to export it as a PDF or copy-paste the text."]];
            }
            break;
    }

    $text = trim($text);
    if (empty($text)) {
        error_log("Empty text extraction for [{$originalName}], extension: {$extension}");
        throw new Exception("Could not extract any text from the file '{$originalName}'. It might be empty, image-based, or use fonts that cannot be parsed.");
    }

    $toc = "";
    if ($apiKey && ($extension === 'pdf' || $extension === 'docx')) {
        if (strlen($text) > 8000) {
            $toc = extractTableOfContents($filePath, $extension, $apiKey);
        }
    }

    if ($apiKey && strlen($text) > 12000) {
        $text = compactDocumentText($text, $apiKey);
    } else {
        $maxLength = 15000;
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength) . "\n\n... [File content truncated] ...\n\n";
        }
    }

    $combined_text = "Context from uploaded file '{$originalName}':\n";
    if (!empty($toc)) {
        $combined_text .= "### DOCUMENT STRUCTURE / TABLE OF CONTENTS\n{$toc}\n---\n";
    }
    $combined_text .= "### CONTENT PREVIEW / SUMMARY\n{$text}\n---\n";

    return [['text' => $combined_text]];
}
