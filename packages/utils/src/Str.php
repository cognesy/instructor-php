<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Cognesy\Pipeline\Legacy\Chain\RawChain;

/**
 * String manipulation utilities.
 */
class Str
{
    /**
     * Splits a string into an array by a delimiter.
     *
     * @param string $input The input string.
     * @param string $delimiter The delimiter.
     * @return array The array of strings.
     */
    static public function split(string $input, string $delimiter = ' ') : array {
        return explode($delimiter, $input);
    }

    /**
     * Joins an array of strings into a single string.
     *
     * @param array $input The array of strings.
     * @param string $glue The glue.
     * @return string The joined string.
     */
    static public function pascal(string $input) : string {
        // turn any case into pascal case
        $normalized = self::spaceSeparated($input);
        return str_replace(' ', '', ucwords($normalized));
    }

    /**
     * Converts a string to snake_case.
     *
     * @param string $input The input string.
     * @return string The snake_case string.
     */
    static public function snake(string $input) : string {
        // turn any case into snake case
        $normalized = self::spaceSeparated($input);
        $lowered = strtolower($normalized);
        return str_replace(' ', '_', $lowered);
    }

    /**
     * Converts a string to camelCase.
     *
     * @param string $input The input string.
     * @return string The camelCase string.
     */
    static public function camel(string $input) : string {
        // turn any case into camel case
        $normalized = self::spaceSeparated($input);
        $camelized = str_replace(' ', '', ucwords($normalized));
        return lcfirst($camelized);
    }

    /**
     * Converts a string to kebab-case.
     *
     * @param string $input The input string.
     * @return string The kebab-case string.
     */
    static public function kebab(string $input) : string {
        // turn any case into kebab case
        $normalized = self::spaceSeparated($input);
        $lowered = strtolower($normalized);
        return str_replace(' ', '-', $lowered);
    }

    /**
     * Converts a string to Title Case.
     *
     * @param string $input The input string.
     * @return string The Title Case string.
     */
    static public function title(string $input) : string {
        // turn any case into title case
        $normalized = self::spaceSeparated($input);
        return ucwords($normalized);
    }

    /**
     * Converts a string to Sentence case.
     *
     * @param string $input The input string.
     * @return string The Sentence case string.
     */
    static private function spaceSeparated(string $input) : string {
        return (new RawChain)->through([
            // separate groups of capitalized words
            fn ($data) => preg_replace('/([A-Z])([a-z])/', ' $1$2', $data),
            // de-camel
            //fn ($data) => preg_replace('/([A-Z]{2,})([A-Z])([a-z])/', '$1 $2$3', $data),
            //fn ($data) => preg_replace('/([a-z])([A-Z])([a-z])/', '$1 $2$3', $data),
            // separate groups of capitalized words of 2+ characters with spaces
            fn ($data) => preg_replace('/([A-Z]{2,})/', ' $1 ', $data),
            // de-kebab
            fn ($data) => str_replace('-', ' ', $data),
            // de-snake
            fn ($data) => str_replace('_', ' ', $data),
            // remove double spaces
            fn ($data) => preg_replace('/\s+/', ' ', $data),
            // remove leading _
            fn ($data) => ltrim($data, '_'),
            // remove leading -
            fn ($data) => ltrim($data, '-'),
            // trim space
            fn ($data) => trim($data),
        ])->process($input);
    }

    /**
     * Checks if a string contains another string.
     *
     * @param string $haystack The string to search in.
     * @param string $needle The string to search for.
     * @param bool $caseSensitive True for case-sensitive search, false otherwise.
     * @return bool True if the needle is found, false otherwise.
     */
    static public function contains(string $haystack, string $needle, bool $caseSensitive = true) : bool {
        return match($caseSensitive) {
            true => strpos($haystack, $needle) !== false,
            false => stripos($haystack, $needle) !== false,
        };
    }

    /**
     * Checks if a string contains all of the specified substrings.
     *
     * @param string $haystack The string to search in.
     * @param string|array $needles The substrings to search for.
     * @param bool $caseSensitive True for case-sensitive search, false otherwise.
     * @return bool True if all substrings are found, false otherwise.
     */
    static public function containsAll(string $haystack, string|array $needles, bool $caseSensitive = true) : bool {
        $needles = is_string($needles) ? [$needles] : $needles;
        foreach($needles as $item) {
            $result = Str::contains($haystack, $item, $caseSensitive);
            if (!$result) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if a string contains any of the specified substrings.
     *
     * @param string $haystack The string to search in.
     * @param string|array $needles The substrings to search for.
     * @param bool $caseSensitive True for case-sensitive search, false otherwise.
     * @return bool True if any substring is found, false otherwise.
     */
    static public function containsAny(string $haystack, string|array $needles, bool $caseSensitive = true) : bool {
        $needles = is_string($needles) ? [$needles] : $needles;
        foreach($needles as $item) {
            $result = Str::contains($haystack, $item, $caseSensitive);
            if ($result) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a string starts with a prefix.
     *
     * @param string $text The string to check.
     * @param string $prefix The prefix to check for.
     * @return bool True if the string starts with the prefix, false otherwise.
     */
    public static function startsWith(string $text, string $prefix) : bool {
        return substr($text, 0, strlen($prefix)) === $prefix;
    }

    /**
     * Checks if a string ends with a suffix.
     *
     * @param string $text The string to check.
     * @param string $suffix The suffix to check for.
     * @return bool True if the string ends with the suffix, false otherwise.
     */
    public static function endsWith(string $text, string $suffix) : bool {
        return substr($text, -strlen($suffix)) === $suffix;
    }

    /**
     * Extracts a substring from a string between two needles.
     *
     * @param string $text The text to search in.
     * @param string $firstNeedle The first needle.
     * @param string $nextNeedle The next needle.
     * @return string The extracted substring.
     */
    public static function between(mixed $text, string $firstNeedle, string $nextNeedle) : string {
        $start = strpos($text, $firstNeedle);
        if ($start === false) {
            return '';
        }
        $start += strlen($firstNeedle);
        $end = strpos($text, $nextNeedle, $start);
        if ($end === false) {
            return '';
        }
        return substr($text, $start, $end - $start);
    }

    /**
     * Extracts a substring from a string before a needle.
     *
     * @param string $text The text to search in.
     * @param string $needle The needle.
     * @return string The extracted substring.
     */
    public static function after(mixed $text, string $needle) : string {
        $start = strpos($text, $needle);
        if ($start === false) {
            return '';
        }
        $start += strlen($needle);
        return substr($text, $start);
    }

    /**
     * Extracts a substring from a string after a needle.
     *
     * @param string $text The text to search in.
     * @param string $needle The needle.
     * @return string The extracted substring.
     */
    public static function when(bool $condition, string $onTrue, string $onFalse) : string {
        return match($condition) {
            true => $onTrue,
            default => $onFalse,
        };
    }

    /**
     * Extracts a substring from a string before a needle.
     *
     * @param string $text The text to search in.
     * @param string $needle The needle.
     * @return string The extracted substring.
     */
    public static function limit(
        string $text,
        int    $limit,
        string $cutMarker = '...',
        int    $align = STR_PAD_RIGHT,
        bool   $fit = true
    ) : string {
        $short = ($align === STR_PAD_LEFT)
            ? substr($text, -$limit)
            : substr($text, 0, $limit);

        if ($text === $short) {
            return $text;
        }

        $cutLength = strlen($cutMarker);
        if ($fit && $cutLength > 0) {
            return ($align === STR_PAD_LEFT)
                ? $cutMarker . substr($short, $cutLength)
                : substr($short, 0, -$cutLength) . $cutMarker;
        }

        return ($align === STR_PAD_LEFT)
            ? $cutMarker . $short
            : $short . $cutMarker;
    }
}
