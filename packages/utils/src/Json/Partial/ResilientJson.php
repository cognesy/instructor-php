<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

/**
 * ResilientJson::parse("...") → array|string|int|float|bool|null
 *
 * Strategy:
 *  1) Try native json_decode fast path
 *  2) Apply minimal repairs and retry
 *  3) Fallback to a tolerant single-pass parser with object/array frames
 */
final class ResilientJson
{
    public static function parse(string $input): array|string|int|float|bool|null {
        // 1) Fast path
        $sliced = self::sliceFromFirstJsonChar($input);
        if ($sliced !== '') {
            try {
                return json_decode($sliced, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) { /* continue */
            }
        }

        // 2) Minimal repairs + retry
        $repaired = self::repairCommonIssues($sliced ?: $input);
        try {
            return json_decode($repaired, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) { /* continue */
        }

        // 3) Lenient parser
        return (new LenientParser())->parse($input);
    }

    private static function sliceFromFirstJsonChar(string $s): string {
        $i = strcspn($s, '{[');
        return $i < strlen($s) ? substr($s, $i) : '';
    }

    private static function repairCommonIssues(string $s): string {
        if ($s === '') return $s;

        // Ensure we start at first plausible JSON root
        $s = self::sliceFromFirstJsonChar($s);

        // Unbalanced quotes (very small heuristic: count unescaped ")
        $quoteCount = preg_match_all('/(?<!\\\\)"/', $s);
        if (is_int($quoteCount) && $quoteCount % 2 === 1) {
            $s .= '"';
        }

        // Remove trailing commas before } or ]
        $s = preg_replace('/,(\s*[}\]])/', '$1', $s) ?? $s;

        // Balance braces/brackets by appending closers
        $opens = substr_count($s, '{');
        $closes = substr_count($s, '}');
        if ($opens > $closes) $s .= str_repeat('}', $opens - $closes);
        $opens = substr_count($s, '[');
        $closes = substr_count($s, ']');
        if ($opens > $closes) $s .= str_repeat(']', $opens - $closes);

        return $s;
    }
}