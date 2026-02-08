<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

use Exception;

/**
 * Class JsonParser
 *
 * Provides methods to extract and parse JSON data from input text, including complete,
 * partial, and malformed JSON strings. Supports multiple extraction techniques such
 * as scanning for fenced JSON blocks, matching curly braces, and handling JSON-like strings.
 */
class JsonParser
{
    /**
     * Attempt to find and parse a complete valid JSON string in the input.
     * Returns a JSON-encoded string on success or an empty string on failure.
     */
    public function findCompleteJson(string $input): string {
        $extractors = [
            fn($text) => [$text],                   // Try as-is
            fn($text) => $this->findByMarkdown($text),
            fn($text) => [$this->findByBrackets($text)],
            fn($text) => $this->findJSONLikeStrings($text),
        ];

        foreach ($extractors as $extractor) {
            $candidates = $extractor($input);
            if (empty($candidates)) {
                continue;
            }

            foreach ($candidates as $candidate) {
                if (!is_string($candidate) || trim($candidate) === '') {
                    continue;
                }

                $data = $this->tryParse($candidate, allowPartial: false);
                if ($data !== null) {
                    // Re-encode in canonical JSON form
                    $result = json_encode($data);
                    if ($result !== false) {
                        return $result;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Attempt to find and parse a partial JSON string in the input.
     * Returns a JSON-encoded string on success or an empty string on failure.
     */
    public function findPartialJson(string $input): string {
        $extractors = [
            fn($text) => [$text],                          // Try as-is
            fn($text) => $this->findPartialByMarkdown($text),
            fn($text) => [$this->findPartialByBrackets($text)],
            fn($text) => $this->findJSONLikeStrings($text),
        ];

        foreach ($extractors as $extractor) {
            $candidates = $extractor($input);
            if (empty($candidates)) {
                continue;
            }

            foreach ($candidates as $candidate) {
                if (!is_string($candidate) || trim($candidate) === '') {
                    continue;
                }

                $normalizedCandidate = $this->normalizePartialCandidate($candidate);
                $data = $this->tryParse($normalizedCandidate, allowPartial: true);
                if ($data !== null) {
                    // Re-encode in canonical JSON form
                    $result = json_encode($data);
                    if ($result !== false) {
                        return $result;
                    }
                }
            }
        }

        return '';
    }

    // ------------------------------------------------------------------------
    // INTERNAL PARSE HELPERS
    // ------------------------------------------------------------------------

    /**
     * Attempt multiple parsing strategies:
     * 1) Standard json_decode with exceptions (JSON_THROW_ON_ERROR)
     * 2) PartialJsonParser (custom)
     * 3) ResilientJsonParser (custom)
     *
     * Returns an associative array on success, or null if all strategies fail.
     */
    private function tryParse(string $maybeJson, bool $allowPartial): mixed {
        $normalized = $this->stripJsonComments($maybeJson);
        $parsers = [
            fn($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            fn($json) => (new ResilientJsonParser($json))->parse(),
        ];

        if ($allowPartial) {
            $parsers[] = fn($json) => (new PartialJsonParser)->parse($json);
        }

        foreach ($parsers as $parser) {
            try {
                $data = $parser($normalized);
            } catch (Exception $e) {
                continue;
            }
            // If parse result is false, null, or empty string, skip
            if ($data === null || $data === false || $data === '') {
                continue;
            }
            if (!is_array($data) && !is_object($data)) {
                continue;
            }
            return $data;
        }

        return null;
    }

    // ------------------------------------------------------------------------
    // MARKDOWN EXTRACTORS
    // ------------------------------------------------------------------------

    /**
     * Find ALL fenced code blocks that start with ```json, and extract
     * the portion between the first '{' and the matching last '}' inside
     * that block. Return an array of candidates.
     */
    private function findByMarkdown(string $text): array {
        if (empty(trim($text))) {
            return [];
        }

        $candidates = [];
        $offset = 0;
        $fenceTag = '```json';

        while (($startFence = strpos($text, $fenceTag, $offset)) !== false) {
            // Find the next triple-backtick fence AFTER the "```json"
            $closeFence = strpos($text, '```', $startFence + strlen($fenceTag));
            if ($closeFence === false) {
                // No closing fence found, stop scanning
                break;
            }

            // Substring that represents the code block between ```json and next ```
            $codeBlock = substr(
                $text,
                $startFence + strlen($fenceTag),
                $closeFence - ($startFence + strlen($fenceTag)),
            );

            // Now find the first '{' and last '}' within this code block
            $firstBrace = strpos($codeBlock, '{');
            $lastBrace = strrpos($codeBlock, '}');
            if ($firstBrace !== false && $lastBrace !== false && $firstBrace < $lastBrace) {
                $jsonCandidate = substr($codeBlock, $firstBrace, $lastBrace - $firstBrace + 1);
                $candidates[] = $jsonCandidate;
            }

            // Advance offset past the closing fence, so we can find subsequent code blocks
            $offset = $closeFence + 3; // skip '```'
        }

        return $candidates;
    }

    /**
     * A "partial" version of findByMarkdown. Here we assume we might only
     * have partial fences or partial JSON. We’ll try to extract the first
     * valid-looking portion we find. For simplicity, this can be similar
     * to findByMarkdown but returning the first match or an array of partials.
     */
    private function findPartialByMarkdown(string $text): array {
        // We’ll just reuse the same logic but allow partial extraction
        // from each code fence. In practice, “partial” might imply more
        // lenient or partial bracket matching, but here's a simplified approach.

        return $this->findByMarkdown($text);
    }

    // ------------------------------------------------------------------------
    // CURLY BRACKET EXTRACTORS
    // ------------------------------------------------------------------------

    /**
     * Find a substring from the first '{' to the last '}'.
     */
    private function findByBrackets(string $text): string {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        $firstOpen = strpos($trimmed, '{');
        if ($firstOpen === false) {
            return '';
        }
        $lastClose = strrpos($trimmed, '}');
        if ($lastClose === false || $lastClose < $firstOpen) {
            return '';
        }

        return substr($trimmed, $firstOpen, $lastClose - $firstOpen + 1);
    }

    /**
     * A "partial" version that tries the same approach, but might allow
     * any trailing text after '}'.
     */
    private function findPartialByBrackets(string $text): string {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        $firstOpen = strpos($trimmed, '{');
        if ($firstOpen === false) {
            return '';
        }
        $lastClose = strrpos($trimmed, '}');
        if ($lastClose === false || $lastClose < $firstOpen) {
            return '';
        }

        return substr($trimmed, $firstOpen, $lastClose - $firstOpen + 1);
    }

    // ------------------------------------------------------------------------
    // FALLBACK EXTRACTOR FOR ANY JSON-LIKE BRACES
    // ------------------------------------------------------------------------

    /**
     * Scan through the text, capturing any substring that begins at '{'
     * and ends at its matching '}'—accounting for nested braces and strings.
     * Returns an array of all such candidates found.
     */
    private function findJSONLikeStrings(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $candidates = [];
        $stack = [];
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($char === '"' && !$escape) {
                $inString = !$inString;
            }

            $escape = ($char === '\\' && !$escape);

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $stack[] = $i;
                continue;
            }

            if ($char === '}' && $stack !== []) {
                $start = array_pop($stack);
                if (!is_int($start)) {
                    continue;
                }
                $candidates[] = substr($text, $start, $i - $start + 1);
            }
        }

        return $candidates;
    }

    private function stripJsonComments(string $text): string {
        $result = '';
        $length = strlen($text);
        $inString = false;
        $escape = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            $next = ($i + 1 < $length) ? $text[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n" || $char === "\r") {
                    $inLineComment = false;
                    $result .= $char;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inString && $char === '/' && $next === '/') {
                $inLineComment = true;
                $i++;
                continue;
            }

            if (!$inString && $char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }

            $result .= $char;

            if ($char === '"' && !$escape) {
                $inString = !$inString;
            }
            $escape = ($char === '\\' && !$escape);
        }

        return $result;
    }

    private function normalizePartialCandidate(string $candidate): string {
        $normalized = preg_replace('/,\s*,+/', ',', $candidate) ?? $candidate;
        $normalized = preg_replace('/,\s*([}\]])/', '$1', $normalized) ?? $normalized;
        return $normalized;
    }
}
