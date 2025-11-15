<?php
// Enable error reporting for debugging during development
if (defined('PREDICTOR_SCRIPT')) {
    // This file is being included by predict.php, so just make functions available and stop.
    return;
}
error_reporting(E_ALL & ~E_DEPRECATED); // Report all errors except for deprecation notices
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'check_auth.php'; // Secure all API endpoints

// --- ALWAYS require the autoloader first ---
require_once 'db_mysql.php';
require 'vendor/autoload.php';

header('Content-Type: application/json');

if (!function_exists('formatResponse')) {
    function formatResponse($text) {
        // --- STAGE 0: Protect quotation marks from MathJax interpretation ---
        // Replace straight quotes with a special marker that we'll restore on the client side
        // This prevents MathJax from treating quotes as math delimiters
        $text = str_replace('"', '@@QUOTE@@', $text);

        // --- STAGE 0B: Fix common plain-text math notation patterns ---
        // This is a safety net in case the AI doesn't follow LaTeX instructions.
        // Wrap patterns like "a2+b2=c2" in LaTeX delimiters.
        // Patterns to fix:
        // 1. Variable with superscript digits: a2, b3, c^2, x^3, etc.
        // 2. Simple equations like "a2+b2=c2" surrounded by spaces or punctuation
        // 3. Avoid matching things already in code blocks or existing LaTeX
        
        // Pattern: variables with digit superscripts (a2, b3, x2, etc.) followed by +/-/= and more similar
        // E.g., "a2+b2=c2" should become "$a^2 + b^2 = c^2$"
        // But avoid: "version 2.0", "item2" at start of line, "test2value"
        
        // First, wrap patterns like "a2 + b2 = c2" in LaTeX if they look like equations
        $text = preg_replace_callback(
            '/\b([a-z])\d+\s*([+\-=])\s*([a-z])\d+\s*([+\-=])\s*([a-z])\d+\b/i',
            function ($matches) {
                // This looks like a multi-term equation with variables and digit superscripts
                // Convert "a2 + b2 = c2" to "$a^2 + b^2 = c^2$"
                $var1 = $matches[1];
                $op1  = $matches[2];
                $var2 = $matches[3];
                $op2  = $matches[4];
                $var3 = $matches[5];
                
                // Extract the digits from the original match
                preg_match('/([a-z])(\d+)\s*([+\-=])\s*([a-z])(\d+)\s*([+\-=])\s*([a-z])(\d+)/i', $matches[0], $parts);
                if (count($parts) >= 9) {
                    $var1_num = $parts[2]; // First digit(s)
                    $var2_num = $parts[5]; // Second digit(s)
                    $var3_num = $parts[8]; // Third digit(s)
                    return "$$" . $var1 . "^{" . $var1_num . "} " . $op1 . " " . $var2 . "^{" . $var2_num . "} " . $op2 . " " . $var3 . "^{" . $var3_num . "}$$";
                }
                return $matches[0];
            },
            $text
        );

        // Also wrap single-variable equations like "c=5", "x=25" (with optional spaces)
        $text = preg_replace_callback(
            '/\b([a-z])\s*=\s*(\d+)\b/i',
            function ($matches) {
                return "$" . $matches[1] . " = " . $matches[2] . "$";
            },
            $text
        );

        // --- STAGE 0C: Fix common AI word concatenation errors ---
        // Pattern 1: "orCathetus", "andSomething" - common word + capital word
        $commonPrefixes = ['or', 'and', 'the', 'a', 'an', 'in', 'is', 'as', 'be', 'do', 'if', 'no', 'so', 'to', 'up', 'we'];
        foreach ($commonPrefixes as $prefix) {
            $pattern = '/(\b' . preg_quote($prefix) . ')([A-Z][a-z]+)/';
            $text = preg_replace($pattern, '$1 $2', $text);
        }
        
        // Pattern 2: "aandb" style - direct fixes for common patterns
        $text = str_replace('aandb', 'a and b', $text); 
        $text = str_replace('andab', 'and ab', $text);
        
        // Pattern 3: "ASimpleExample" - single capital letter followed by capital letter + word
        $text = preg_replace_callback(
            '/([A-Z])([A-Z][a-z]+)/s',
            function ($matches) {
                $first = $matches[1];
                $rest = $matches[2];
                return $first . ' ' . $rest;
            },
            $text
        );
        
        // Pattern 4: Consecutive title-case words
        $text = preg_replace_callback(
            '/([a-z])([A-Z][a-z]+)/s',
            function ($matches) {
                return $matches[1] . ' ' . $matches[2];
            },
            $text
        );

        // --- STAGE 1: Protect all inline code (backticks) from Markdown processing ---
        // This prevents Parsedown from treating * or _ inside backticks as emphasis markers
        $backtickProtections = [];
        $backtickCounter = 0;
        $text = preg_replace_callback(
            '/`[^`]+`/s',
            function ($matches) use (&$backtickProtections, &$backtickCounter) {
                $placeholder = '@@BACKTICK_' . $backtickCounter . '@@';
                $backtickProtections[$placeholder] = $matches[0];
                $backtickCounter++;
                return $placeholder;
            },
            $text
        );

        // --- STAGE 1B: Protect parenthesized text from Markdown and MathJax processing ---
        // Protects plain text in parentheses from being misinterpreted as emphasis by Markdown
        // or as math by MathJax.
        $parenProtections = [];
        $parenCounter = 0;
        $text = preg_replace_callback(
            '/\(([^)]*)\)/s',
            function ($matches) use (&$parenProtections, &$parenCounter) {
                $inner = $matches[1];

                // If the content looks like LaTeX or code, leave it for the next stages.
                if (strpos($inner, '$') !== false || strpos($inner, '\\') !== false || strpos($inner, '`') !== false) {
                    return $matches[0];
                }

                // For plain text, escape Markdown emphasis characters.
                $escaped = str_replace(['*', '_'], ['&#42;', '&#95;'], $inner);
                
                $placeholder = '@@PAREN_' . $parenCounter . '@@';

                // MODIFICATION: Wrap the content in a span with the 'no-mathjax' class.
                // This is the most reliable way to prevent MathJax from processing the content.
                // The zero-width space hack was not consistently effective.
                $parenProtections[$placeholder] = '(<span class="no-mathjax">' . $escaped . '</span>)';
                $parenCounter++;
                return $placeholder;
            },
            $text
        );

        // --- STAGE 2: Protect LaTeX from Parsedown ---
        $latexProtections = [];
        $latexCounter = 0;

        // First protect display math ($$...$$ and \[...\])
        $text = preg_replace_callback(
            '/\$\$(.*?)\$\$|\\\\\[(.*?)\\\\\]/s',
            function ($matches) use (&$latexProtections, &$latexCounter) {
                $placeholder = '@@LATEX_' . $latexCounter . '@@';
                $latexProtections[$placeholder] = $matches[0];
                $latexCounter++;
                return $placeholder;
            },
            $text
        );

        // Then protect inline math (\(...\) and $...$)
        $text = preg_replace_callback(
            '/\\\\\((.*?)\\\\\)|\$([^\$]+)\$/s',
            function ($matches) use (&$latexProtections, &$latexCounter) {
                $full_match = $matches[0];
                $start_delim = substr($full_match, 0, 2) === '\\(' ? '\\(' : '$';
                
                // Get the content (group 1 for \(...\), group 2 for $...$)
                $content = $matches[1] !== '' ? $matches[1] : $matches[2];
                
                // Handle $...$ validation
                if ($start_delim === '$') {
                    // This is a common heuristic to avoid matching prices like "$10" or "$20".
                    // If the content has spaces at the start/end, it's probably not math.
                    $has_spaces = preg_match('/^\s|\s$/', $content);

                    if (empty($content) || $has_spaces) {
                        return $full_match; // Don't treat as LaTeX
                    }
                }
                
                // For \(...\), we can be less strict as it's a more explicit delimiter.
                // We'll accept it as LaTeX unless the content is completely empty.
                if ($start_delim === '\\(') {
                    if (empty(trim($content))) {
                        return $full_match; // Don't protect empty delimiters
                    }
                }
                
                $placeholder = '@@LATEX_' . $latexCounter . '@@';
                $latexProtections[$placeholder] = $full_match;
                $latexCounter++;
                return $placeholder;
            },
            $text
        );

        // --- STAGE 3: Process with Parsedown ---
        $Parsedown = new Parsedown();
        $Parsedown->setBreaksEnabled(true);
        $html = $Parsedown->text($text);

        // --- STAGE 4: Clean up unnecessary emphasis tags around LaTeX BEFORE restoring ---
        // Remove <strong> and <em> tags that wrap LaTeX placeholders
        $html = preg_replace('/<strong>(@@LATEX_\d+@@)<\/strong>/s', '$1', $html);
        $html = preg_replace('/<em>(@@LATEX_\d+@@)<\/em>/s', '$1', $html);

        // --- STAGE 5: Restore LaTeX ---
        foreach ($latexProtections as $placeholder => $latex) {
            // Decide whether this LaTeX should be displayed as block math or inline math.
            // If it's already display math ($$...$$ or \[...\]) leave as-is.
            $isDisplay = (substr($latex, 0, 2) === '$$' || substr($latex, 0, 2) === '\\[');

            if (!$isDisplay) {
                // Extract the inner content for inspection
                $inner = $latex;
                // Remove delimiters for $...$ and \(...\)
                if (substr($inner, 0, 1) === '$' && substr($inner, -1) === '$') {
                    $inner = substr($inner, 1, -1);
                } elseif (substr($inner, 0, 2) === '\\(' && substr($inner, -2) === '\\)') {
                    $inner = substr($inner, 2, -2);
                }

                $trimmed = trim($inner);

                // Heuristic: promote to display math if it looks like a block equation.
                $looksLikeEquation = false;
                
                // Promote for structures that are typically display-style.
                if (preg_match('/\\\\frac|\\\\sum|\\\\int|\\\\begin/', $trimmed)) {
                    $looksLikeEquation = true;
                }
                
                // Promote if it contains an equals sign and is not very short.
                if (!$looksLikeEquation && strpos($trimmed, '=') !== false && strlen($trimmed) > 10) {
                    $looksLikeEquation = true;
                }

                // Promote if it contains line breaks or is very long.
                if (!$looksLikeEquation && (strpos($trimmed, "\n") !== false || strlen($trimmed) > 120)) {
                    $looksLikeEquation = true;
                }

                if ($looksLikeEquation) {
                    // Convert to display math using $$...$$
                    $latex = '$$' . $trimmed . '$$';
                    $isDisplay = true;
                } else {
                    // Keep inline and wrap in a span for MathJax isolation
                    $latex = '<span class="math inline">' . $latex . '</span>';
                }
            }

            $html = str_replace($placeholder, $latex, $html);
        }

        // --- STAGE 6: Restore backticks (wrapped in <code> tags) ---
        foreach ($backtickProtections as $placeholder => $backtick) {
            // Remove the backticks and wrap the content in <code> tags
            $codeContent = substr($backtick, 1, -1); // Remove the backticks
            $html = str_replace($placeholder, '<code>' . htmlspecialchars($codeContent, ENT_QUOTES, 'UTF-8') . '</code>', $html);
        }

        // --- STAGE 6B: Restore protected parenthesized text ---
        // These were protected before Parsedown to prevent unwanted emphasis inside parentheses.
        foreach ($parenProtections as $placeholder => $paren) {
            $html = str_replace($placeholder, $paren, $html);
        }

        // --- STAGE 7: Clean up any remaining emphasis tags around LaTeX expressions ---
        // Match: <strong>$...$</strong> or <em>$...$</em> or similar with other delimiters
        $html = preg_replace('/<strong>(\$\$.*?\$\$)<\/strong>/s', '$1', $html);
        $html = preg_replace('/<em>(\$\$.*?\$\$)<\/em>/s', '$1', $html);
        $html = preg_replace('/<strong>(\\\\\[.*?\\\\\])<\/strong>/s', '$1', $html);
        $html = preg_replace('/<em>(\\\\\[.*?\\\\\])<\/em>/s', '$1', $html);
        $html = preg_replace('/<strong>(\\\\\(.*?\\\\\))<\/strong>/s', '$1', $html);
        $html = preg_replace('/<em>(\\\\\(.*?\\\\\))<\/em>/s', '$1', $html);
        $html = preg_replace('/<strong>(\$[^$]+\$)<\/strong>/s', '$1', $html);
        $html = preg_replace('/<em>(\$[^$]+\$)<\/em>/s', '$1', $html);

    // --- STAGE 7B: Fix unwanted emphasis inside or around parentheses ---
    // Parsedown can sometimes turn underscores or surrounding characters into <em> inside parentheses.
    // Remove emphasis tags that wrap entire parenthesized fragments, or wrap content directly inside parentheses.
    // Examples handled:
    //   <em>(some text)</em>  -> (some text)
    //   (<em>some text</em>)  -> (some text)
    // Also handle <strong> similarly.
    $html = preg_replace('/<em>\((.*?)\)<\/em>/s', '($1)', $html);
    $html = preg_replace('/\(<em>(.*?)<\/em>\)/s', '($1)', $html);
    $html = preg_replace('/<strong>\((.*?)\)<\/strong>/s', '($1)', $html);
    $html = preg_replace('/\(<strong>(.*?)<\/strong>\)/s', '($1)', $html);

        // --- STAGE 8: Ensure LaTeX is not wrapped in unnecessary <p> tags ---
        // MathJax CHTML renderer can have issues with LaTeX inside <p> tags
        // Move display math outside of paragraphs
        
        // First, handle pure display math paragraphs: <p>$$...$</p> or <p>\[...\]</p>
        $html = preg_replace('/<p>(\s*)(\$\$.*?\$\$)(\s*)<\/p>/s', '$2', $html);
        $html = preg_replace('/<p>(\s*)(\\\\\[.*?\\\\\])(\s*)<\/p>/s', '$2', $html);

        // Second, handle mixed content: split <p> tags when they contain display math
        // For example: <p>Using it: $$3^2 + 4^2 = c^2$$</p> becomes:
        //              <p>Using it:</p>\n$$3^2 + 4^2 = c^2$$\n
        $html = preg_replace_callback(
            '/<p>((?:[^<]|<(?!\/p>))*?\$\$(?:[^<]|<(?!\/p>))*?\$\$(?:[^<]|<(?!\/p>))*?)<\/p>/s',
            function ($matches) {
                $content = $matches[1];
                
                // Split on display math delimiters ($$...$$)
                $parts = preg_split('/(\$\$[^\$]*(?:\$[^\$])*\$\$)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
                
                $result = '';
                foreach ($parts as $part) {
                    if (empty($part)) continue;
                    
                    if (strpos($part, '$$') === 0) {
                        // This is display math - output it on its own line, outside <p>
                        $result .= "\n" . $part . "\n";
                    } else {
                        // This is text - wrap it in <p> if not empty
                        $text = trim($part);
                        if ($text) {
                            $result .= '<p>' . $text . '</p>';
                        }
                    }
                }
                return rtrim($result);
            },
            $html
        );

        // --- Final HTML Cleanup ---
        $html = preg_replace('/<li(>|\s[^>]*>)\s*<p>(.*?)<\/p>\s*<\/li>/s', '<li$1>$2</li>', $html);
        $html = preg_replace('/<li>&gt;/', '<li>', $html);

        // --- STAGE 9: Restore quotation marks (now that MathJax processing is complete) ---
        $html = str_replace('@@QUOTE@@', '"', $html);

        // --- STAGE 10: Remove emphasis elements that wrongly cover parenthesized text ---
        // Use DOMDocument to safely unwrap <em> and <strong> nodes whose text contains parentheses
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        // Wrap HTML in a container to allow fragment parsing; add XML encoding to preserve UTF-8
        $wrapped = '<?xml encoding="utf-8" ?><div id="__wrapper__">' . $html . '</div>';
        if (!$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            return $html; // Return original HTML on parsing failure
        }
        $xpath = new DOMXPath($doc);

        foreach (['em', 'strong'] as $tag) {
            $nodes = $xpath->query('//' . $tag);
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                if (!$node) continue;
                $text = $node->textContent;
                if (strpos($text, '(') !== false || strpos($text, ')') !== false) {
                    $parent = $node->parentNode;
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                }
            }
        }

        $wrapper = $doc->getElementById('__wrapper__');
        $newHtml = '';
        if ($wrapper) {
            foreach ($wrapper->childNodes as $child) {
                $newHtml .= $doc->saveHTML($child);
            }
        } else {
            $newHtml = $doc->saveHTML();
        }

        libxml_clear_errors();

        return $newHtml;
    }
}

$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to handle GET and POST actions

// Add a special case for logout to be handled by auth.php
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.html');
    exit;
}

if ($action) {
    switch ($action) { // This switch now handles GET and some POST actions
        case 'history':
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id, title FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
                $stmt->execute([$_SESSION['user_id']]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'history' => $history]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not fetch history.']);
            }
            break;

        case 'get_conversation':
            $convo_id = $_GET['id'] ?? null;
            if (!$convo_id) {
                echo json_encode(['success' => false, 'error' => 'Conversation ID is missing.']);
                break;
            }
            try {
                $pdo = getDbConnection();
                // First, verify the user owns this conversation
                $stmt = $pdo->prepare("SELECT id, title FROM conversations WHERE id = ? AND user_id = ?");
                $stmt->execute([$convo_id, $_SESSION['user_id']]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$conversation) {
                    echo json_encode(['success' => false, 'error' => 'Conversation not found or access denied.']);
                    break;
                }

                // Fetch all messages for this conversation
                $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
                $stmt->execute([$convo_id]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $conversation['chat_history'] = [];
                foreach ($messages as $message) {
                    // The 'content' is a JSON string of the 'parts' array, so we decode it.
                    $parts = json_decode($message['content'], true);
                    if ($message['role'] === 'model') {
                        $parts[0]['text'] = formatResponse($parts[0]['text']);
                    }
                    $conversation['chat_history'][] = ['role' => $message['role'], 'parts' => $parts];
                }

                echo json_encode(['success' => true, 'conversation' => $conversation]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not fetch conversation.']);
            }
            break;

        case 'delete_conversation':
            $convo_id = $_GET['id'] ?? null;
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ? AND user_id = ?");
            $stmt->execute([$convo_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;
        
        case 'rename_conversation':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
                break;
            }
            $convo_id = $_POST['id'] ?? null;
            $new_title = trim($_POST['title'] ?? '');

            if (!$convo_id || empty($new_title)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing conversation ID or title.']);
                break;
            }

            $pdo = getDbConnection();
            $stmt = $pdo->prepare("UPDATE conversations SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_title, $convo_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
            break;
    }
    exit;
}

// --- Main Chat Logic (handles POST requests without an 'action' parameter) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method for chat.']);
    exit;
}

// --- Check for large file upload error ---
// If the request method is POST but the POST and FILES arrays are empty, it's a classic sign
// that the upload exceeded the server's post_max_size limit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    throw new Exception("The uploaded file is too large. It exceeds the server's configured limit.");
}

$question = $_POST['question'] ?? '';
$learningLevel = $_POST['learningLevel'] ?? 'Understanding';

$conversation_id = $_POST['conversation_id'] ?? null;

function prepareFileParts($file, $user_question) {
    $filePath = $file['tmp_name'];
    $fileType = mime_content_type($filePath);
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed_types = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'bmp'  => 'image/bmp',
        'webp' => 'image/webp',
    ];

    if (!in_array($extension, array_keys($allowed_types))) {
        // Let's be more generic in the error message now
        throw new Exception("Unsupported file type: {$extension}.");
    }

    // Double-check MIME type
    if (!in_array($fileType, $allowed_types)) {
         // Allow for some variation in MIME types reported by servers
        if ($extension !== 'docx' || $fileType !== 'application/zip') {
            throw new Exception("File content does not match its extension ({$extension} vs {$fileType}).");
        }
    }

    // Handle images
    if (strpos($fileType, 'image/') === 0) {
        if (!extension_loaded('gd')) {
            throw new Exception("The 'gd' PHP extension is required to process images but it is not enabled. Please enable it in your php.ini file.");
        }
        $fileData = file_get_contents($filePath);
        if ($fileData === false) {
            throw new Exception("Could not read the image file '{$originalName}'.");
        }
        $base64Data = base64_encode($fileData);

        return [
            ['inline_data' => ['mime_type' => $fileType, 'data' => $base64Data]],
            ['text' => $user_question]
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
            break;
        case 'docx':
            if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
                throw new Exception("Word document parsing library is not installed. Please run 'composer require phpoffice/phpword'.");
            }
            if (!extension_loaded('zip')) {
                throw new Exception("The 'zip' PHP extension is required to read .docx files but it is not enabled. Please enable it in your php.ini file.");
            }
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $textExtractor = new \PhpOffice\PhpWord\Shared\Html();
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach($element->getElements() as $textElement) {
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
            // Increase memory limit for large PPTX files (some presentations are memory-intensive)
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '1G');
            try {
                $phpPresentation = \PhpOffice\PhpPresentation\IOFactory::load($filePath);
            } finally {
                // Restore original memory limit
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

    if (empty($text)) {
        throw new Exception("Could not extract any text from the file '{$originalName}'. It might be empty, image-based, or corrupted.");
    }

    // Truncate to a reasonable length to avoid excessive API costs/limits
    $maxLength = 20000; // Approx 5000 tokens
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength) . "\n\n... [File content truncated] ...\n\n";
    }

    $combined_text = "Context from uploaded file '{$originalName}':\n---\n{$text}\n---\n\nUser's question: {$user_question}";
    return [
        ['text' => $combined_text]
    ];
}

function callGeminiAPI($payload, $apiKey) {
    $model = 'gemini-2.5-flash-preview-05-20';
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    $retries = 0;
    $max_retries = 5;
    $delay = 1;

    while ($retries < $max_retries) {
        if (!function_exists('curl_init')) {
            throw new Exception('PHP cURL extension is not enabled.');
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) throw new Exception('cURL Error: ' . $curl_error); // Still throw for actual cURL errors
        if ($http_status === 429 || $http_status === 503) { // Rate limit or Service Unavailable
            $retries++;
            if ($retries >= $max_retries) throw new Exception('AI service rate limit exceeded.');
            sleep($delay);
            $delay *= 2;
            continue;
        } // Add this closing brace
        if ($http_status !== 200) throw new Exception('AI service returned an error: HTTP ' . $http_status . ' - ' . substr($response, 0, 200));

        return json_decode($response, true);
    }
}

try {
    // Initialize variables that might be used in conditional paths to avoid warnings
    $a = null;
    $x = null;

    $pdo = getDbConnection();

    // --- Secure API Key Handling ---
    // Try to load config-sql.ini first, then fall back to config.ini
    $config = null;
    $configFiles = ['config-sql.ini', 'config.ini'];
    
    foreach ($configFiles as $configFile) {
        if (file_exists($configFile)) {
            $config = parse_ini_file($configFile);
            if ($config !== false) {
                break; // Successfully loaded, exit loop
            }
        }
    }
    
    if ($config === false || !isset($config['GEMINI_API_KEY'])) {
        throw new Exception('API key configuration is missing or unreadable in config-sql.ini or config.ini.');
    }
    define('GEMINI_API_KEY', $config['GEMINI_API_KEY']);

    if (GEMINI_API_KEY === 'YOUR_ACTUAL_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API Key is not configured in config.ini.');
    }

    $user_message_parts = [];

    // Check for file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $user_message_parts = prepareFileParts($_FILES['attachment'], $question);
    } else {
        $user_message_parts[] = ['text' => $question];
    }

    // Basic validation
    $is_empty = empty($user_message_parts) || (count($user_message_parts) === 1 && empty(trim($user_message_parts[0]['text'])));
    if ($is_empty) {
        echo json_encode(['success' => false, 'error' => 'Question is missing or file content is empty.']);
        exit;
    }

    // If no conversation ID, create a new one in the database
    if (!$conversation_id) {
        $stmt = $pdo->prepare("INSERT INTO conversations (user_id, title) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'New Chat on ' . date('Y-m-d')]);
        $conversation_id = $pdo->lastInsertId();
    } else {
        // Verify the user owns the conversation they are trying to post to.
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$conversation_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Conversation access denied.");
        }
    }

    // Save user message to the database
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)");
    $stmt->execute([$conversation_id, json_encode($user_message_parts)]);

    // Fetch the conversation history from the database to send to the AI
    $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$conversation_id]);
    $db_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chat_history = [];
    foreach ($db_messages as $message) {
        $chat_history[] = [
            'role' => $message['role'],
            'parts' => json_decode($message['content'], true)
        ];
    }

    // Add the new user message (with its parts) to the history
    // If possible, respond immediately to the client and continue processing
    // in the background to avoid client-side timeouts for slow AI responses.
    $backgrounded = false;
    if (function_exists('fastcgi_finish_request')) {
        $backgrounded = true;
        $ack = ['success' => true, 'processing' => true, 'conversation_id' => $conversation_id];
        header('Content-Type: application/json');
        echo json_encode($ack);
        // flush all response data to the client and finish the request
        fastcgi_finish_request();
        // script continues to run after this point to call the AI and save results
    }

    // System prompt
    $system_prompt = <<<PROMPT
# Adaptive AI Tutor System Prompt

You are an expert AI tutor designed to facilitate deep learning across any subject. Your goal is not just to provide answers, but to guide learners toward understanding through adaptive, personalized instruction.

## Core Philosophy

- **Learning > Answers**: Prioritize understanding over quick solutions
- **Adaptive**: Continuously adjust to the learner's needs
- **Socratic**: Use questions to guide discovery when appropriate
- **Encouraging**: Build confidence and maintain engagement
- **Metacognitive**: Help learners understand their own thinking

---

## PHASE 1: ASSESS THE LEARNER

Before responding, analyze the learner's message. The user has indicated a desired learning goal based on Bloom's Taxonomy: **{$learningLevel}**. Use this as a starting point, but adapt based on your analysis of their actual message.

### A. Knowledge State Indicators

**General Proficiency**:
- **Novice**: Vague questions, missing vocabulary, fundamental confusion
- **Developing**: Partial understanding, specific confusion points, some correct terminology
- **Proficient**: Detailed questions, mostly correct understanding, seeking nuance
- **Expert**: Deep questions, looking for edge cases or advanced applications

**Bloom's Taxonomy Level** (Cognitive Dimension):
Identify which level(s) the learner is operating at or needs to reach:

1.  **Remember**: Recall facts, terms, basic concepts. Keywords: "what is", "define", "list".
2.  **Understand**: Explain ideas, interpret meaning, summarize. Keywords: "explain", "describe", "why".
3.  **Apply**: Use information in new situations, solve problems. Keywords: "calculate", "solve", "what happens if".
4.  **Analyze**: Draw connections, distinguish between parts. Keywords: "compare", "contrast", "examine".
5.  **Evaluate**: Justify decisions, make judgments, critique. Keywords: "assess", "judge", "which is better".
6.  **Create**: Generate new ideas, design solutions. Keywords: "design", "create", "propose".

**Target Bloom's Level**: Where should you guide them?
- The user's stated goal is **{$learningLevel}**.
- If their question seems below this level, help them build up to it.
- If their question is already at or above this level, engage them there.
- Build foundations before advancing. Don't jump more than 1-2 levels in a single interaction.

### B. Interaction Intent
- **Seeking explanation**: "What is...", "Can you explain..."
- **Seeking confirmation**: "Is this correct?"
- **Stuck on problem**: "I'm stuck on...", shows work
- **Seeking challenge**: "What's a harder problem?"
- **Exploring curiosity**: "Why...", "What if..."

### C. Emotional/Motivational State
- **Frustrated**: Negative language, giving up signals
- **Confused**: Contradictory statements, uncertainty
- **Confident**: Assertive statements, ready for more
- **Curious**: Exploratory questions, enthusiasm

### D. Error Pattern Recognition
- **Conceptual**: Fundamental misunderstanding
- **Procedural**: Knows concept but wrong steps
- **Careless**: Simple mistake, likely understands

---

## PHASE 2: SELECT STRATEGY

Based on assessment, choose your pedagogical approach:

| Learner State | Primary Strategy |
|---|---|
| Novice seeking explanation | **Direct Teaching** with examples |
| Developing, specific confusion | **Socratic Questioning** |
| Proficient, seeking nuance | **Elaborative Discussion** |
| Stuck on problem | **Scaffolded Guidance** |
| Made an error | **Diagnostic Questions** |
| Showing mastery | **Challenge Extension** |
| Frustrated | **Encouraging Reset** |
| Curious exploration | **Guided Discovery** |

### Teaching Strategies Defined

1.  **Direct Teaching**: Clear, structured explanation with examples and analogies. Check for understanding.
2.  **Socratic Questioning**: Guide through strategic questions to help them discover answers.
3.  **Scaffolded Guidance**: Start with minimal hints, gradually increasing support.
4.  **Diagnostic Questions**: Ask questions that reveal thinking ("How did you get that?"). Guide to self-correction.
5.  **Elaborative Discussion**: Explore implications and connections ("How does this relate to...?" ).
6.  **Challenge Extension**: Pose harder problems or introduce advanced applications.

---

## DISCIPLINE-SPECIFIC ENHANCEMENTS

When you detect the subject area, apply these additional strategies on top of your primary strategy:

### IF MATHEMATICS:
- Always explain WHY procedures work, not just HOW.
- Use multiple representations (numerical, algebraic, graphical, verbal).
- When students make errors, ask diagnostic questions before correcting.
- Guide through: Understand → Plan → Execute → Check.
- Never let them just memorize formulas without understanding.
- **CRITICAL: Use LaTeX for ALL mathematical notation and formulas.** For inline math, wrap with `$...$` or `\(...\)`. For display/block equations, use `$$...$$` or `\[...\]`.
- **Examples of CORRECT format:**
  - Pythagorean theorem (inline): The formula is $a^2 + b^2 = c^2$ where...
  - Pythagorean theorem (display): $$a^2 + b^2 = c^2$$
  - Square root: $\sqrt{25} = 5$
  - Exponents: $2^3 = 8$ and $x^2 - 4 = 0$
  - Fractions: $\frac{1}{2}$ for inline, or $$\frac{a^2 + b^2}{c}$$ for display
- **NEVER write:** a2+b2=c2 or 3^2 as plain text. **ALWAYS wrap in LaTeX.**
- When showing calculations step-by-step, wrap each formula in LaTeX delimiters.

### IF SCIENCE (Physics, Chemistry, Biology):
- Start with observable phenomena before abstract explanations.
- Connect macroscopic (what we see) to microscopic (atoms/cells/particles).
- Actively confront common misconceptions.
- Build mental models through prediction and testing.
- Always ask "What's happening at the [molecular/atomic/cellular] level?"

### IF BIOLOGY specifically:
- Emphasize structure-function relationships ("Why does it exist? What's its purpose?").
- Walk through processes step-by-step with causation ("which causes... leading to...").
- Don't just teach vocabulary - teach the concepts, terminology follows.
- Connect to evolution ("What survival advantage does this provide?").

### IF HUMANITIES (History, Literature, Philosophy):
- Multiple valid interpretations exist, but all need textual evidence.
- Always ask "What evidence from the text/source supports that?".
- Emphasize historical/cultural context.
- Build arguments: Claim → Evidence → Reasoning → Counterargument.
- Ask "What would someone from that time period have thought?".

### IF PROGRAMMING:
- Focus on computational thinking first, syntax second.
- Normalize errors: "Errors are feedback, not failure".
- Guide through: Understand → Examples → Decompose → Pseudocode → Code.
- When debugging: "What did you expect? What actually happened? Where's the gap?".
- Ask them to read/trace code before writing it.

---

## PHASE 3: CRAFT YOUR RESPONSE

### Response Structure Template

```
[Optional: Brief acknowledgment of their effort/emotional state]
[Main instructional content - tailored to strategy]
[Engagement element: question, challenge, or check for understanding]
[Optional: Encouragement or next steps]
```

### Response Guidelines

- **Tone**: Patient for novices, supportive for developing, collegial for proficient, reassuring for frustrated.
- **Language**: Match their vocabulary. Introduce technical terms with definitions. Use analogies.
- **Scaffolding Levels** (for problem-solving):
    1.  **Metacognitive Prompt**: "What have you tried so far?"
    2.  **Directional Hint**: "Think about how [concept] applies here."
    3.  **Strategic Hint**: "Try breaking this into smaller steps."
    4.  **Partial Solution**: "Let's start with... can you continue?"
    5.  **Worked Example** (Last resort): Show a full solution, then ask them to try a similar problem.

---

## PHASE 4: ADAPTIVE FOLLOW-UP

- **If They Understand**: Acknowledge success, reinforce, and extend ("Now try this variation...").
- **If Still Confused**: Don't repeat. Try a different approach (analogy, simpler language). Ask diagnostic questions.
- **If They Made Progress**: Celebrate progress and provide a targeted hint for the next step.
- **If They're Frustrated**: Normalize the struggle, reframe what they DO understand, and simplify to rebuild confidence.

---

## SPECIAL SCENARIOS

### When They Ask for Direct Answer
**Don't immediately comply**. Instead:
1.  "I want to help you learn this, not just give you the answer. Let me guide you."
2.  "What do you understand so far?"
3.  If truly stuck after scaffolding, provide the answer with a thorough explanation and follow up with a similar problem for them to solve.

### When They Share Wrong Work/Thinking
**Never say "That's wrong" directly**. Instead:
1.  "I can see your thinking here..."
2.  Ask diagnostically: "Can you walk me through why you chose...?"
3.  Guide them to see the error themselves.

### When They Ask Homework Questions
1.  Never solve homework directly.
2.  State: "I'll help you learn to solve it yourself."
3.  Use the scaffolding approach to teach the method, not the specific answer.

---

## QUALITY CHECKS

Before sending your response, verify:
- [ ] Did I assess their knowledge state, using their stated goal of **{$learningLevel}** as a guide?
- [ ] Did I choose an appropriate strategy?
- [ ] Am I facilitating learning, not just giving answers?
- [ ] Is my language and tone appropriate?
- [ ] Did I include an engagement element (a question or challenge)?
- [ ] Have I avoided robbing them of the "aha!" moment?

Remember: You are a **learning facilitator**. Your success is measured by how deeply you help learners understand.
PROMPT;
    // Construct the prompt for the AI
    $payload = json_encode([
        "contents" => $chat_history,
        "system_instruction" => [
            "role" => "system",
            "parts" => [["text" => $system_prompt]]
        ]
    ]);

    $responseData = callGeminiAPI($payload, GEMINI_API_KEY);

    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $responseData['candidates'][0]['content']['parts'][0]['text'];

        // Save AI response to the database
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'model', ?)");
        $stmt->execute([$conversation_id, json_encode([['text' => $answer]])]);

        // For new chats, we will generate the title on the client-side for faster response.
        // We'll pass a flag to the client to let it know a new title is needed.
        $new_title_for_response = null;
        if (count($chat_history) === 1) { 
            // This is a new chat. The client will generate and send the title.
            // We send back the original user question to help generate the title.
            $new_title_for_response = true; // Flag for the client
        }

        $formattedAnswer = formatResponse($answer);
        $response_payload = [
            'success' => true, 
            'answer' => $formattedAnswer, 
            'conversation_id' => $conversation_id
        ];
        if ($new_title_for_response) {
            $response_payload['is_new_chat'] = true;
            $response_payload['user_question'] = $question;
        }
        if (empty($backgrounded)) {
            echo json_encode($response_payload);
            exit();
        } else {
            // We already sent an acknowledgement to the client and finished the request.
            // Background worker (this continuing PHP process) will finish saving the model
            // response above. End the script quietly.
            return;
        }
    } else {
        error_log("Gemini API: Unexpected response structure - " . print_r($responseData, true));
        throw new Exception('No content generated or unexpected response structure from AI.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
}
