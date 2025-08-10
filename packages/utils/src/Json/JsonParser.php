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
            if (is_string($candidates)) {
                $candidates = [$candidates];
            }

            foreach ($candidates as $candidate) {
                if (!is_string($candidate) || trim($candidate) === '') {
                    continue;
                }

                $data = $this->tryParse($candidate);
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

            if (is_string($candidates)) {
                $candidates = [$candidates];
            }

            foreach ($candidates as $candidate) {
                if (!is_string($candidate) || trim($candidate) === '') {
                    continue;
                }

                $data = $this->tryParse($candidate);
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
    private function tryParse(string $maybeJson): mixed {
        $parsers = [
            fn($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            //fn($json) => ResilientJson::parse($json),
            fn($json) => (new ResilientJsonParser($json))->parse(),
            fn($json) => (new PartialJsonParser)->parse($json),
        ];

        foreach ($parsers as $parser) {
            try {
                $data = $parser($maybeJson);
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
        $currentCandidate = '';
        $bracketCount = 0;
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if (!$inString) {
                if ($char === '{') {
                    if ($bracketCount === 0) {
                        $currentCandidate = '';
                    }
                    $bracketCount++;
                } elseif ($char === '}') {
                    $bracketCount--;
                }
            }

            // Toggle inString if we encounter an unescaped quote
            if ($char === '"' && !$escape) {
                $inString = !$inString;
            }

            // Determine if current char is a backslash for next iteration
            $escape = ($char === '\\' && !$escape);

            if ($bracketCount > 0) {
                $currentCandidate .= $char;
            }

            // If bracketCount just went back to 0, we've closed a JSON-like block
            if ($bracketCount === 0 && $currentCandidate !== '') {
                $currentCandidate .= $char; // include the closing '}'
                $candidates[] = $currentCandidate;
                $currentCandidate = '';
            }
        }

        return $candidates;
    }
}
