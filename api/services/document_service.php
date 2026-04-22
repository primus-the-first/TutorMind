<?php
/**
 * Document & OCR Service
 * Handles file parsing (PDF, DOCX, PPTX, images) and OCR fallback chain.
 */

function ocrImageBasedPdf($pdfPath, $originalName) {
    $config = null;
    foreach ([__DIR__ . '/../../includes/config-sql.ini', __DIR__ . '/../../includes/config.ini'] as $configFile) {
        if (file_exists($configFile)) {
            $config = parse_ini_file($configFile);
            if ($config !== false) break;
        }
    }

    $errors = [];

    // 1. Try Google Cloud Vision API (primary)
    if (!empty($config['GOOGLE_CLOUD_VISION_API_KEY'])) {
        try {
            $text = ocrWithGoogleCloudVision($pdfPath, $config['GOOGLE_CLOUD_VISION_API_KEY']);
            if (!empty(trim($text))) {
                error_log("OCR [{$originalName}]: Success with Google Cloud Vision");
                return $text;
            }
        } catch (Exception $e) {
            $errors[] = "Google Cloud Vision: " . $e->getMessage();
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
            $errors[] = "OCR.space: " . $e->getMessage();
            error_log("OCR.space OCR failed: " . $e->getMessage());
        }
    }

    // 3. Try Tesseract (local fallback - last resort)
    try {
        $text = ocrWithTesseract($pdfPath, $originalName);
        if (!empty(trim($text))) {
            error_log("OCR [{$originalName}]: Success with Tesseract (local)");
            return $text;
        }
    } catch (Exception $e) {
        $errors[] = "Tesseract: " . $e->getMessage();
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

function ocrWithTesseract($pdfPath, $originalName) {
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

    if (!in_array($fileType, $allowed_types)) {
        if ($extension !== 'docx' || $fileType !== 'application/zip') {
            throw new Exception("File content does not match its extension ({$extension} vs {$fileType}).");
        }
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

    $text = '';
    switch ($extension) {
        case 'txt':
            $text = file_get_contents($filePath);
            break;
        case 'pdf':
            if (!class_exists('\Smalot\PdfParser\Parser')) {
                throw new Exception("PDF parsing library is not installed. Please run 'composer require smalot/pdfparser'.");
            }
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            $pageCount = count($pdf->getPages());
            error_log("PDF [{$originalName}]: {$pageCount} pages, " . strlen($text) . " chars extracted");

            if (empty(trim($text)) && $pageCount > 0) {
                foreach ($pdf->getPages() as $page) {
                    $text .= $page->getText() . "\n\n";
                }
            }

            if (empty(trim($text))) {
                $text = ocrImageBasedPdf($filePath, $originalName);
            }
            break;
        case 'docx':
            if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
                throw new Exception("Word document parsing library is not installed. Please run 'composer require phpoffice/phpword'.");
            }
            if (!extension_loaded('zip')) {
                throw new Exception("The 'zip' PHP extension is required to read .docx files but it is not enabled. Please enable it in your php.ini file.");
            }
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $textElement->getText() . ' ';
                            }
                        }
                    }
                }
            }
            break;
        case 'pptx':
            if (!class_exists('\PhpOffice\PhpPresentation\IOFactory')) {
                throw new Exception("PowerPoint parsing library is not installed. Please run 'composer require phpoffice/phppresentation'.");
            }
            if (!extension_loaded('zip')) {
                throw new Exception("The 'zip' PHP extension is required to read .pptx files but it is not enabled. Please enable it in your php.ini file.");
            }
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '1G');
            try {
                $phpPresentation = \PhpOffice\PhpPresentation\IOFactory::load($filePath);
            } finally {
                ini_set('memory_limit', $originalMemoryLimit);
            }
            foreach ($phpPresentation->getAllSlides() as $slide) {
                foreach ($slide->getShapeCollection() as $shape) {
                    if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                        $text .= $shape->getPlainText() . "\n\n";
                    }
                }
            }
            break;
    }

    $text = trim($text);
    if (empty($text)) {
        error_log("Empty text extraction for [{$originalName}], extension: {$extension}");
        throw new Exception("Could not extract any text from the file '{$originalName}'. It might be empty, image-based (scanned), or use fonts that cannot be parsed.");
    }

    $maxLength = 20000;
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength) . "\n\n... [File content truncated] ...\n\n";
    }

    $combined_text = "Context from uploaded file '{$originalName}':\n---\n{$text}\n---\n";
    return [
        'text' => $combined_text
    ];
}
