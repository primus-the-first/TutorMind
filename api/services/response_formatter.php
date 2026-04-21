<?php
/**
 * Response Formatter Service
 * Converts raw AI markdown text to safe HTML, with LaTeX and code block protection.
 */

if (!function_exists('formatResponse')) {
    function formatResponse($text)
    {
        $protections = [];
        $counter = 0;

        // IMPORTANT: Protect code blocks FIRST, before LaTeX or inline code
        // This prevents ${variables} in code from being caught by LaTeX $ protection
        // Regex notes:
        //   [ \t]* after language: tolerates trailing spaces Gemini sometimes adds
        //   \r?    before \n:      handles Windows-style CRLF line endings
        $text = preg_replace_callback(
            '/```([\w+\-#.]*)[ \t]*\r?\n([\s\S]*?)```/s',
            function ($matches) use (&$protections, &$counter) {
                $placeholder = '@@PROTECT_' . $counter . '@@';
                $language = $matches[1]; // Language identifier (optional)
                $codeContent = $matches[2]; // The actual code
                $protections[$placeholder] = [
                    'type' => 'codeblock',
                    'language' => $language,
                    'content' => $codeContent
                ];
                $counter++;
                return $placeholder;
            },
            $text
        );

        // Protect inline code (`) - before LaTeX
        $text = preg_replace_callback(
            '/`([^`]+)`/s',
            function ($matches) use (&$protections, &$counter) {
                $placeholder = '@@PROTECT_' . $counter . '@@';
                // Store the inner content of the code block
                $protections[$placeholder] = ['type' => 'code', 'content' => $matches[1]];
                $counter++;
                return $placeholder;
            },
            $text
        );

        // Protect LaTeX expressions LAST (after all code is protected)
        $text = preg_replace_callback(
            '/\$\$([\s\S]*?)\$\$|\\\\\[([\s\S]*?)\\\\\]|\\\\\((.*?)\\\\\)|\$([^$]+?)\$/',
            function ($matches) use (&$protections, &$counter) {
                $placeholder = '@@PROTECT_' . $counter . '@@';
                // Store the original content, which includes the delimiters
                $protections[$placeholder] = ['type' => 'latex', 'content' => $matches[0]];
                $counter++;
                return $placeholder;
            },
            $text
        );

        // Process with Parsedown (requires composer vendor; falls back to nl2br if unavailable)
        if (class_exists('Parsedown')) {
            $Parsedown = new Parsedown();
            $Parsedown->setBreaksEnabled(true);
            $html = $Parsedown->text($text);
        } else {
            $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        }

        // Restore protected content
        foreach ($protections as $placeholder => $protection) {
            if ($protection['type'] === 'latex') {
                $html = str_replace($placeholder, $protection['content'], $html);
            } elseif ($protection['type'] === 'codeblock') {
                // Restore code blocks with proper HTML
                // NOTE: class goes on <code>, not <pre>, so hljs/addCopyButtonsToCodeBlocks can read it
                $codeContent = htmlspecialchars($protection['content'], ENT_QUOTES, 'UTF-8');
                $language = !empty($protection['language']) ? ' class="language-' . htmlspecialchars($protection['language']) . '"' : '';
                $codeBlockHtml = '<pre><code' . $language . '>' . $codeContent . '</code></pre>';
                $html = str_replace($placeholder, $codeBlockHtml, $html);
            } elseif ($protection['type'] === 'code') {
                $codeContent = htmlspecialchars($protection['content'], ENT_QUOTES, 'UTF-8');
                $html = str_replace($placeholder, '<code>' . $codeContent . '</code>', $html);
            }
        }

        // Final cleanup: remove <p> tags from around display math
        $html = preg_replace('/<p>(\s*)(\$\$.*?\$\$)(\s*)<\/p>/s', '$2', $html);
        $html = preg_replace('/<p>(\s*)(\\\\\[.*?\\\\\])(\s*)<\/p>/s', '$2', $html);
        // Remove <p> wrappers Parsedown adds around block-level <pre> elements
        // (browsers auto-correct <p><pre> but leave dangling empty <p> tags and extra spacing)
        $html = preg_replace('/<p>\s*(<pre[\s>])/s', '$1', $html);
        $html = preg_replace('/(<\/pre>)\s*<\/p>/s', '$1', $html);

        return $html;
    }
}
