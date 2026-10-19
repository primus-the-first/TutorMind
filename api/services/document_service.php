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

    // Extract ytInitialPlayerResponse by finding the opening brace and counting
    // braces to its close — more robust than a regex over the full JSON blob.
    if (!preg_match('/ytInitialPlayerResponse\s*=\s*\{/s', $html, $m, PREG_OFFSET_CAPTURE)) {
        error_log("YouTube transcript: ytInitialPlayerResponse not found in page (len=" . strlen($html) . ")");
        return '';
    }
    $start = $m[0][1] + strlen($m[0][0]) - 1; // position of the opening {
    $depth    = 0;
    $end      = $start;
    $len      = strlen($html);
    $inString = false;
    $escape   = false;
    for ($i = $start; $i < $len; $i++) {
        $ch = $html[$i];
        if ($escape) { $escape = false; continue; }
        if ($ch === '\\' && $inString) { $escape = true; continue; }
        if ($ch === '"') { $inString = !$inString; continue; }
        if ($inString) continue;
        if ($ch === '{')      $depth++;
        elseif ($ch === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
    }
    if ($depth !== 0) {
        error_log("YouTube transcript: unmatched braces in ytInitialPlayerResponse");
        return '';
    }

    $player = json_decode(substr($html, $start, $end - $start + 1), true);
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
        // cPanel / CloudLinux (Namecheap production)
        '/opt/alt/python311/bin/python3',
        '/opt/alt/python310/bin/python3',
        '/opt/alt/python39/bin/python3',
        // Windows (XAMPP localhost)
        'C:\\Python314\\python.exe',
        'C:\\Python313\\python.exe',
        'C:\\Python312\\python.exe',
        'C:\\Python311\\python.exe',
        'C:\\Python310\\python.exe',
        'C:\\Python39\\python.exe',
        // Generic fallback
        'python3', 'python', 'py',
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
    // Set HOME so Python can locate user site-packages installed via pip --user,
    // even when exec() runs under the web server process with a stripped environment.
    $home   = '/home/' . get_current_user();
    $envPrefix = is_dir($home) ? "HOME={$home} " : '';
    $cmd    = "{$envPrefix}{$python} -X utf8 {$script} {$arg} 2>&1";

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

/**
 * Extract text from a PDF using smalot/pdfparser (pure PHP, no exec).
 * Returns extracted text, or empty string if parsing fails or yields nothing.
 */
function extractPdfWithParser(string $pdfPath): string
{
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($pdfPath);
        $text   = $pdf->getText();
        return $text ?? '';
    } catch (\Throwable $e) {
        error_log("smalot/pdfparser error: " . $e->getMessage());
        return '';
    }
}

/** Every key from both config files; the first file that sets a key to something wins.
 *  Stopping at the first file found hid keys that only live in the other one (the OCR
 *  keys sat in config.ini while config-sql.ini was read, so OCR never ran). */
function documentConfig(): array {
    static $config = null;
    if ($config !== null) return $config;
    $config = [];
    foreach ([__DIR__ . '/../../includes/config-sql.ini', __DIR__ . '/../../includes/config.ini'] as $configFile) {
        if (!file_exists($configFile) || ($c = parse_ini_file($configFile)) === false) continue;
        foreach ($c as $k => $v) {
            if (!isset($config[$k]) || trim((string) $config[$k]) === '') $config[$k] = $v;
        }
    }
    return $config;
}

function ocrImageBasedPdf($pdfPath, $originalName) {
    $config = documentConfig();

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
 * Resize raw image bytes to fit within 600×600 and re-encode as JPEG.
 * Returns ['bytes' => string, 'mime' => string], or null if GD can't load the image.
 * - When GD is unavailable the original bytes are returned with the provided MIME.
 * - When the image is already within the limit it is still re-encoded as JPEG.
 */
function resizeImageData(string $raw, string $originalMime = 'image/jpeg', int $max = 600): ?array
{
    if (!extension_loaded('gd')) {
        // No GD — pass through as-is, preserving the original MIME type
        return ['bytes' => $raw, 'mime' => $originalMime];
    }

    $src = @imagecreatefromstring($raw);
    if (!$src) return null;

    $ow = imagesx($src);
    $oh = imagesy($src);

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

    if (!$jpeg) return null;
    return ['bytes' => $jpeg, 'mime' => 'image/jpeg'];
}

/** A part's relationships: [rId => ['target' => ..., 'type' => last segment of its Type]]. */
function pptxRels(ZipArchive $zip, string $relsPath): array
{
    $xml = $zip->getFromName($relsPath);
    if ($xml === false) return [];
    preg_match_all('/<Relationship\b[^>]*>/i', $xml, $m);
    $out = [];
    foreach ($m[0] as $tag) {
        if (preg_match('/\bId="([^"]+)"/', $tag, $id) && preg_match('/\bTarget="([^"]+)"/', $tag, $t)) {
            $out[$id[1]] = ['target' => $t[1], 'type' => preg_match('#\bType="[^"]*/([^"/]+)"#', $tag, $ty) ? $ty[1] : ''];
        }
    }
    return $out;
}

/** A relationship target resolved against the part that points at it ("../media/x.png"). */
function pptxResolve(string $base, string $target): string
{
    $stack = [];
    foreach (explode('/', dirname($base) . '/' . $target) as $part) {
        if ($part === '..') array_pop($stack);
        elseif ($part !== '.' && $part !== '') $stack[] = $part;
    }
    return implode('/', $stack);
}

/** The deck's slide parts in the order it shows them (presentation.xml's slide list). */
function pptxSlidePaths(ZipArchive $zip): array
{
    $slides = [];
    $presRels = pptxRels($zip, 'ppt/_rels/presentation.xml.rels');
    $pres = $zip->getFromName('ppt/presentation.xml');
    if ($pres !== false && preg_match_all('/<p:sldId\b[^>]*\br:id="([^"]+)"/', $pres, $ids)) {
        foreach ($ids[1] as $rid) {
            if (isset($presRels[$rid])) $slides[] = pptxResolve('ppt/presentation.xml', $presRels[$rid]['target']);
        }
    }
    if (!$slides) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $zip->getNameIndex($i), $m)) $slides[(int) $m[1]] = $m[0];
        }
        ksort($slides);
        $slides = array_values($slides);
    }
    return $slides;
}

/** The paragraphs of a DrawingML part, one string each, skipping field codes (slide numbers). */
function pptxParagraphs(string $xml): array
{
    $xml = preg_replace('#<a:fld\b.*?</a:fld>#s', '', $xml);
    preg_match_all('#<a:p\b[^>]*>(.*?)</a:p>#s', $xml, $paras);
    $out = [];
    foreach ($paras[1] as $p) {
        preg_match_all('#<a:t>([^<]*)</a:t>#', $p, $runs);
        $line = trim(html_entity_decode(implode('', $runs[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if ($line !== '') $out[] = $line;
    }
    return $out;
}

/**
 * A deck's typed text read straight from its XML, in slide order, with speaker notes:
 * the same "<!-- Slide number: N -->" layout MarkItDown writes. MarkItDown runs Python,
 * and when that fails on the server it returns '' quietly; without this, a deck full of
 * typed text was then treated as "pictures only" and read from its decorations.
 */
function extractPptxTextWithPhp(string $filePath): string
{
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return '';
    $out = [];
    foreach (pptxSlidePaths($zip) as $n => $slidePath) {
        $xml = $zip->getFromName($slidePath);
        if ($xml === false) continue;
        $section = "<!-- Slide number: " . ($n + 1) . " -->\n" . implode("\n", pptxParagraphs($xml));
        foreach (pptxRels($zip, dirname($slidePath) . '/_rels/' . basename($slidePath) . '.rels') as $rel) {
            if ($rel['type'] !== 'notesSlide') continue;
            $notes = $zip->getFromName(pptxResolve($slidePath, $rel['target']));
            $lines = $notes !== false ? pptxParagraphs($notes) : [];
            if ($lines) $section .= "\n\n### Notes:\n" . implode("\n", $lines);
        }
        $out[] = $section;
    }
    $zip->close();
    return implode("\n\n", $out);
}

/**
 * The images on each slide, in the order the deck shows them (presentation.xml's slide
 * list, not ppt/media, whose order is arbitrary). An image reused on several slides (a
 * logo) is only taken the first time, and tiny ones (icons, bullets, under 4 KB) are
 * skipped: they hold no text and only cost a reading. $maxSide 600 suits a chat
 * request; OCR wants more. Returns [['slide' => 1-based number, 'mime' => ..., 'data' => base64], ...].
 */
function extractImagesFromPptx(string $filePath, int $maxSide = 600): array
{
    if (!class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return [];

    $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp'];
    $images = [];
    $seen = [];
    foreach (pptxSlidePaths($zip) as $n => $slidePath) {
        foreach (pptxRels($zip, dirname($slidePath) . '/_rels/' . basename($slidePath) . '.rels') as $rel) {
            if ($rel['type'] !== 'image') continue;
            $media = pptxResolve($slidePath, $rel['target']);
            if (isset($seen[$media]) || !preg_match('/\.(' . implode('|', array_keys($mimeMap)) . ')$/i', $media, $ext)) continue;
            $seen[$media] = true;
            $raw = $zip->getFromName($media);
            if ($raw === false || strlen($raw) < 4096) continue;
            $resized = resizeImageData($raw, $mimeMap[strtolower($ext[1])] ?? 'image/jpeg', $maxSide);
            if ($resized === null) continue;
            $images[] = ['slide' => $n + 1, 'mime' => $resized['mime'], 'data' => base64_encode($resized['bytes'])];
        }
    }
    $zip->close();
    return $images;
}

/**
 * Text from a deck whose slides are pictures (exported from Canva, scanned, pasted as
 * screenshots), as "## Slide N" sections. Tried in order: DeepSeek (deepseek-flash reads
 * images; on the paid balance, not Gemini's free tier), Gemini Flash-Lite (both can also
 * say what a diagram shows), then Google Cloud Vision and OCR.space, plain OCR that
 * depends on neither. Returns '' when nothing worked.
 */
// Reading a slide is transcription, not reasoning, so the smallest model does it:
// Flash-Lite reads printed text as well as Flash at a fraction of the price.
const SLIDE_READER_MODEL = 'gemini-3.1-flash-lite';
const SLIDE_MAX_IMAGES = 60;     // one lecture deck; beyond this the student is told what was left out
const SLIDE_GEMINI_BATCH = 10;   // images per DeepSeek / Gemini request
const SLIDE_OCR_PARALLEL = 5;    // OCR.space requests in flight at once
const SLIDE_READER_PROMPT = 'These are the images from a slide deck, labelled with their slide numbers. For each slide, write a heading "## Slide N" and then every piece of text visible on it, keeping headings, bullet points, labels and captions. If a diagram, chart or photo carries meaning, add one line starting "Visual:" saying what it shows. Transcribe only; don\'t add explanations.';

function transcribeSlideImages(array $images, string $originalName): string
{
    if (!$images) return '';
    $skipped = array_slice($images, SLIDE_MAX_IMAGES);
    $images = array_slice($images, 0, SLIDE_MAX_IMAGES);
    // Said in the text itself, so the tutor tells the student instead of answering as if it saw everything
    $coverage = $skipped
        ? "\n\n(Only slides 1-" . end($images)['slide'] . " were read. Slides " . $skipped[0]['slide'] . '-' . end($skipped)['slide']
          . " were not; if the student asks about them, say so and suggest uploading them separately.)"
        : '';
    $config = documentConfig();
    $attempts = [
        'deepseek'     => !empty($config['DEEPSEEK_API_KEY'])            ? fn() => slideTextWithDeepSeek($images, $config['DEEPSEEK_API_KEY']) : null,
        'gemini'       => !empty($config['GEMINI_API_KEY'])              ? fn() => slideTextWithGemini($images, $config['GEMINI_API_KEY']) : null,
        'cloud vision' => !empty($config['GOOGLE_CLOUD_VISION_API_KEY']) ? fn() => slideTextWithCloudVision($images, $config['GOOGLE_CLOUD_VISION_API_KEY']) : null,
        'ocr.space'    => !empty($config['OCR_SPACE_API_KEY'])           ? fn() => slideTextWithOcrSpace($images, $config['OCR_SPACE_API_KEY']) : null,
    ];
    foreach (array_filter($attempts) as $name => $attempt) {
        $t0 = microtime(true);
        try {
            $text = trim($attempt());
            $secs = round(microtime(true) - $t0, 1);
            if ($text !== '') {
                error_log("Slide text [{$originalName}]: {$name} read " . count($images) . " images in {$secs}s");
                return $text . $coverage;
            }
            error_log("Slide text [{$originalName}]: {$name} found no text ({$secs}s)");
        } catch (Throwable $e) {
            error_log("Slide text [{$originalName}]: {$name} failed: " . $e->getMessage());
        }
    }
    return '';
}

/** Group per-image text into "## Slide N" sections. */
function slideSections(array $images, array $texts): string
{
    $bySlide = [];
    foreach ($images as $i => $img) {
        $t = trim($texts[$i] ?? '');
        if ($t !== '') $bySlide[$img['slide'] ?? ($i + 1)][] = $t;
    }
    $out = [];
    foreach ($bySlide as $n => $parts) $out[] = "## Slide {$n}\n" . implode("\n", $parts);
    return implode("\n\n", $out);
}

function slideTextWithDeepSeek(array $images, string $apiKey): string
{
    $out = [];
    foreach (array_chunk($images, SLIDE_GEMINI_BATCH) as $batch) {
        $content = [['type' => 'text', 'text' => SLIDE_READER_PROMPT]];
        foreach ($batch as $img) {
            $content[] = ['type' => 'text', 'text' => 'Slide ' . ($img['slide'] ?? '?') . ':'];
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $img['mime'] . ';base64,' . $img['data']]];
        }
        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'deepseek-flash',
                'messages' => [['role' => 'user', 'content' => $content]],
                'max_tokens' => 8192,
                'thinking' => ['type' => 'disabled'],  // transcription needs no reasoning
            ]),
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code !== 200) throw new Exception("HTTP {$code} {$err} " . substr((string) $response, 0, 200));
        $text = trim(json_decode($response, true)['choices'][0]['message']['content'] ?? '');
        if ($text === '') throw new Exception('a batch came back empty');
        $out[] = $text;
    }
    return implode("\n\n", $out);
}

function slideTextWithGemini(array $images, string $apiKey): string
{
    $out = [];
    foreach (array_chunk($images, SLIDE_GEMINI_BATCH) as $batch) {
        $parts = [];
        foreach ($batch as $img) {
            $parts[] = ['text' => 'Slide ' . ($img['slide'] ?? '?') . ':'];
            $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => $img['data']]];
        }
        $parts[] = ['text' => SLIDE_READER_PROMPT];
        // One short try per batch: if Gemini is overloaded, the OCR services take over the whole deck
        $response = callGeminiAPI(json_encode([
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => ['maxOutputTokens' => 8192, 'temperature' => 0.1],
        ]), $apiKey, 40, 1, SLIDE_READER_MODEL);
        $text = '';
        foreach ($response['candidates'][0]['content']['parts'] ?? [] as $p) $text .= $p['text'] ?? '';
        if (trim($text) === '') throw new Exception('a batch came back empty');
        $out[] = trim($text);
    }
    return implode("\n\n", $out);
}

function slideTextWithCloudVision(array $images, string $apiKey): string
{
    $texts = [];
    foreach (array_chunk($images, 16) as $batch) {  // the API takes at most 16 images per request
        foreach (cloudVisionBatch($batch, $apiKey) as $t) $texts[] = $t;
    }
    return slideSections($images, $texts);
}

function cloudVisionBatch(array $images, string $apiKey): array
{
    $requests = array_map(fn($img) => [
        'image' => ['content' => $img['data']],
        'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
    ], $images);
    $ch = curl_init('https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($apiKey));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['requests' => $requests]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code !== 200) throw new Exception("HTTP {$code} {$err} " . substr((string) $response, 0, 200));
    $data = json_decode($response, true);
    return array_map(fn($r) => $r['fullTextAnnotation']['text'] ?? '', $data['responses'] ?? []);
}

function slideTextWithOcrSpace(array $images, string $apiKey): string
{
    $texts = [];
    foreach (array_chunk($images, SLIDE_OCR_PARALLEL, true) as $batch) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($batch as $i => $img) {
            // The free plan rejects files over 1 MB; a photo-heavy slide gets one smaller retry
            if (strlen($img['data']) * 3 / 4 > 1000 * 1024) {
                $small = resizeImageData(base64_decode($img['data']), $img['mime'], 1000);
                if ($small) $img = ['mime' => $small['mime'], 'data' => base64_encode($small['bytes'])] + $img;
            }
            $ch = curl_init('https://api.ocr.space/parse/image');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'apikey' => $apiKey,
                    'base64Image' => 'data:' . $img['mime'] . ';base64,' . $img['data'],
                    'language' => 'eng',
                    'OCREngine' => '2',
                    'scale' => 'true',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running && $status === CURLM_OK);
        foreach ($handles as $i => $ch) {
            $data = json_decode((string) curl_multi_getcontent($ch), true);
            $texts[$i] = $data['ParsedResults'][0]['ParsedText'] ?? '';
            if (!empty($data['IsErroredOnProcessing'])) {
                error_log('OCR.space slide ' . ($images[$i]['slide'] ?? $i) . ': ' . json_encode($data['ErrorMessage'] ?? 'error'));
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return slideSections($images, $texts);
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
            ['inline_data' => ['mime_type' => $fileType, 'data' => $base64Data]]
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
            $text = extractPdfWithParser($filePath);
            error_log("pdfparser PDF [{$originalName}]: " . strlen($text) . " chars extracted");
            if (empty(trim($text))) {
                $text = extractWithMarkItDown($filePath);
                error_log("MarkItDown PDF [{$originalName}]: " . strlen($text) . " chars extracted");
            }
            if (empty(trim($text))) {
                $text = ocrImageBasedPdf($filePath, $originalName);
            }
            break;
        case 'docx':
            try {
                $text = extractWithMarkItDown($filePath);
                error_log("MarkItDown docx [{$originalName}]: " . strlen($text) . " chars extracted");
            } catch (Exception $e) {
                error_log("MarkItDown docx [{$originalName}] failed ({$e->getMessage()}), falling back to PhpWord");
                try {
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                    $textParts = [];
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if (method_exists($element, 'getText')) {
                                $textParts[] = $element->getText();
                            } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                foreach ($element->getElements() as $child) {
                                    if (method_exists($child, 'getText')) $textParts[] = $child->getText();
                                }
                            }
                        }
                    }
                    $text = implode("\n", array_filter($textParts));
                    error_log("PhpWord docx [{$originalName}]: " . strlen($text) . " chars extracted");
                } catch (Exception $e2) {
                    error_log("PhpWord docx [{$originalName}] also failed: " . $e2->getMessage());
                    $text = '';
                }
            }
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
            if ($strippedText === '') {
                // MarkItDown (Python) can fail quietly on the server; read the XML directly
                $text = extractPptxTextWithPhp($filePath);
                $strippedText = trim(preg_replace('/<!--.*?-->/s', '', $text));
                error_log("PHP pptx reader [{$originalName}]: " . strlen($strippedText) . " chars of typed text");
            }
            if (empty($strippedText)) {
                // Image-only deck (slides are pictures). Read them into text once, here, so
                // every provider can answer, on this message and on every follow-up: a
                // text-only model can't see images, and follow-ups go to DeepSeek.
                $slideImages = extractImagesFromPptx($filePath, 1600);
                $slideText = transcribeSlideImages($slideImages, $originalName);
                error_log("MarkItDown pptx [{$originalName}]: image-only, " . count($slideImages) . " images, " . strlen($slideText) . " chars read from them");
                if ($slideText !== '') {
                    $text = "(This deck has no typed text. The text below was read from its slide images.)\n\n" . $slideText;
                    break;
                }
                if (!empty($slideImages)) {
                    // Nothing could read them: hand the images to the chat (only Gemini sees them)
                    $parts = [['text' => "The following images are slides from the uploaded presentation '{$originalName}'. Analyse their content to answer the user's question. If you can't see any images here, tell the student plainly that the slides couldn't be read this time, and suggest trying again or uploading the deck as a PDF. Don't guess the content from the file name."]];
                    foreach (array_slice($slideImages, 0, 6) as $img) {
                        $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => $img['data']]];
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
