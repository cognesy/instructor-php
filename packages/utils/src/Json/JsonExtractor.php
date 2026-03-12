<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

/**
 * Finds and extracts valid JSON from surrounding text.
 *
 * This is NOT a parser — it locates JSON substrings and validates them
 * with json_decode(). Use JsonDecoder for parsing broken/partial JSON.
 */
final class JsonExtractor
{
    /**
     * Extract and decode the first valid JSON object or array from text.
     * Returns null if no valid JSON found.
     *
     * @return array<array-key, mixed>|null
     */
    public static function first(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        // 1) Try the whole text as-is
        $decoded = self::tryDecode($trimmed);
        if ($decoded !== null) {
            return $decoded;
        }

        // 2) Try markdown code blocks
        foreach (self::extractMarkdownBlocks($trimmed) as $candidate) {
            $decoded = self::tryDecode($candidate);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // 3) Smart brace scanning
        foreach (self::extractByBraceMatching($trimmed) as $candidate) {
            $decoded = self::tryDecode($candidate);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Extract and decode all valid JSON objects/arrays from text.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function all(string $text): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $results = [];
        foreach (self::extractByBraceMatching($trimmed) as $candidate) {
            $decoded = self::tryDecode($candidate);
            if ($decoded !== null) {
                $results[] = $decoded;
            }
        }

        return $results;
    }

    // ── Extraction strategies ────────────────────────────────────────

    /**
     * Try decoding JSON with repair heuristics but without accepting garbage.
     *
     * Uses JsonDecoder::tryStrictDecode() which handles common LLM issues like
     * invalid escape sequences (\R, \T from class names) without creating a
     * circular dependency (tryStrictDecode never calls back into JsonExtractor)
     * and without using the lenient tokenizer (which would accept anything).
     *
     * @return array<array-key, mixed>|null
     */
    private static function tryDecode(string $json): ?array
    {
        $decoded = JsonDecoder::tryStrictDecode($json);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Extract content from ```json ... ``` or ``` ... ``` fenced code blocks.
     *
     * @return list<string>
     */
    private static function extractMarkdownBlocks(string $text): array
    {
        $candidates = [];
        $offset = 0;

        while (($startFence = strpos($text, '```', $offset)) !== false) {
            // Skip the ``` and optional language tag
            $lineEnd = strpos($text, "\n", $startFence);
            if ($lineEnd === false) {
                break;
            }
            $contentStart = $lineEnd + 1;

            // Find closing fence
            $closeFence = strpos($text, '```', $contentStart);
            if ($closeFence === false) {
                // No closing fence — take rest of text
                $block = substr($text, $contentStart);
            } else {
                $block = substr($text, $contentStart, $closeFence - $contentStart);
            }

            $block = trim($block);
            if ($block !== '') {
                $candidates[] = $block;
            }

            $offset = $closeFence !== false ? $closeFence + 3 : strlen($text);
        }

        return $candidates;
    }

    /**
     * Smart brace matching: find JSON objects/arrays by tracking brace depth
     * while respecting quoted strings and escape sequences.
     *
     * @return list<string>
     */
    private static function extractByBraceMatching(string $text): array
    {
        $candidates = [];
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            $ch = $text[$i];

            // Find start of a JSON structure
            if ($ch !== '{' && $ch !== '[') {
                $i++;
                continue;
            }

            $closer = $ch === '{' ? '}' : ']';
            $start = $i;
            $depth = 1;
            $inString = false;
            $escaped = false;
            $i++;

            while ($i < $length && $depth > 0) {
                $c = $text[$i];

                if ($escaped) {
                    $escaped = false;
                    $i++;
                    continue;
                }

                if ($c === '\\') {
                    $escaped = true;
                    $i++;
                    continue;
                }

                if ($c === '"') {
                    $inString = !$inString;
                    $i++;
                    continue;
                }

                if (!$inString) {
                    if ($c === '{' || $c === '[') {
                        $depth++;
                    } elseif ($c === '}' || $c === ']') {
                        $depth--;
                    }
                }
                $i++;
            }

            if ($depth === 0) {
                $candidates[] = substr($text, $start, $i - $start);
            }
        }

        return $candidates;
    }
}
