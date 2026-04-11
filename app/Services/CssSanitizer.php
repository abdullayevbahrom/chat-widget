<?php

namespace App\Services;

class CssSanitizer
{
    /**
     * Dangerous CSS patterns that must be blocked.
     */
    protected array $dangerousPatterns = [
        '/expression\s*\(/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/url\s*\(\s*["\']?\s*javascript\s*:/i',
        '/url\s*\(\s*["\']?\s*vbscript\s*:/i',
        '/@import\s/i',
        '/behavior\s*:/i',
        '/-moz-binding\s*:/i',
        '/binding\s*:/i',
        '/-webkit-binding\s*:/i',
        // Block @property and @supports which can be used for CSS injection attacks
        // to probe for browser features or exfiltrate data
        '/@property\s/i',
        '/@supports\s*\(/i',
        // Block expression() nested inside url() (double-encoding bypass)
        '/url\s*\([^)]*expression\s*\(/i',
    ];

    /**
     * URL patterns that are allowed (whitelist).
     * Only data: images are permitted; all external URLs are blocked
     * to prevent CSS-based data exfiltration attacks.
     */
    protected array $allowedUrlPatterns = [
        '/url\s*\(\s*["\']?\s*data:image\/[a-zA-Z0-9+.-]+;?\s*(base64)?\s*[;,]?[^)]*\)/i',
    ];

    /**
     * Sanitize CSS content to prevent XSS and injection attacks.
     *
     * This is a defense-in-depth measure applied at render time
     * in addition to model-level sanitization.
     */
    public function sanitize(string $css): string
    {
        $css = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $css) ?? '';

        // Strip all HTML tags — CSS should never contain HTML
        $css = strip_tags($css);

        // Strip null bytes
        $css = str_replace("\0", '', $css);

        // Remove comments before inserting block markers for dangerous patterns.
        $css = preg_replace(
            '/\/\*[^*]*\*+(?:[^*\/][^*]*\*+)*\//',
            '',
            $css
        ) ?? '';

        // Remove dangerous CSS patterns
        foreach ($this->dangerousPatterns as $pattern) {
            $css = preg_replace($pattern, '/* blocked */', $css);
        }

        // Block all url() except allowed patterns (data:image/*)
        // This prevents CSS-based data exfiltration attacks
        $css = $this->sanitizeUrls($css);

        // Remove backslash-based escapes that could bypass filters
        // e.g., \65\78\70\72\65\73\73\69\6f\6e (expression in hex escapes)
        $css = $this->removeEscapedAttacks($css);

        return trim($css);
    }

    /**
     * Sanitize CSS url() functions.
     * Only allows data:image/* URLs; blocks all external URLs
     * to prevent data exfiltration via CSS.
     */
    protected function sanitizeUrls(string $css): string
    {
        // Find all url() functions and validate them
        return preg_replace_callback(
            '/url\s*\(\s*([^)]+)\s*\)/i',
            function ($matches) {
                $urlContent = $matches[1];
                $originalUrl = $matches[0];

                // Check against allowed patterns
                foreach ($this->allowedUrlPatterns as $allowedPattern) {
                    if (preg_match($allowedPattern, $originalUrl)) {
                        return $originalUrl;
                    }
                }

                // Block all other URLs
                return '/* url blocked */';
            },
            $css
        );
    }

    /**
     * Remove CSS escaped characters that could bypass filters.
     *
     * Attackers may use CSS hex escapes like \65\78 to spell out
     * dangerous words. We decode and re-check them.
     */
    protected function removeEscapedAttacks(string $css): string
    {
        // Remove CSS escape sequences that could encode dangerous strings
        // Match backslash followed by hex digits (CSS escape syntax)
        $decoded = preg_replace_callback(
            '/\\\\([0-9a-fA-F]{1,6})\s?/',
            function ($matches) {
                $hex = $matches[1];
                $char = mb_chr(hexdec($hex));

                // If the decoded character is alphabetic, block the escape
                if ($char !== null && ctype_alpha($char)) {
                    return '/* escaped-char blocked */';
                }

                return $char ?? '';
            },
            $css
        );

        // Re-check decoded content for dangerous patterns
        foreach ($this->dangerousPatterns as $pattern) {
            $decoded = preg_replace($pattern, '/* blocked */', $decoded);
        }

        return $decoded;
    }

    /**
     * Sanitize CSS from a file path.
     *
     * Returns empty string if file doesn't exist or is unreadable.
     */
    public function sanitizeFile(string $path): string
    {
        if (! file_exists($path) || ! is_readable($path)) {
            return '';
        }

        // Prevent reading excessively large files
        $maxSize = 500 * 1024; // 500KB
        if (filesize($path) > $maxSize) {
            return '';
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return '';
        }

        return $this->sanitize($content);
    }
}
