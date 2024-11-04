<?php

namespace Cognesy\Instructor\Utils;

class Str
{
    static public function split(string $input, string $delimiter = ' ') : array {
        return explode($delimiter, $input);
    }

    static public function pascal(string $input) : string {
        // turn any case into pascal case
        $normalized = self::spaceSeparated($input);
        return str_replace(' ', '', ucwords($normalized));
    }

    static public function snake(string $input) : string {
        // turn any case into snake case
        $normalized = self::spaceSeparated($input);
        $lowered = strtolower($normalized);
        return str_replace(' ', '_', $lowered);
    }

    static public function camel(string $input) : string {
        // turn any case into camel case
        $normalized = self::spaceSeparated($input);
        $camelized = str_replace(' ', '', ucwords($normalized));
        return lcfirst($camelized);
    }

    static public function kebab(string $input) : string {
        // turn any case into kebab case
        $normalized = self::spaceSeparated($input);
        $lowered = strtolower($normalized);
        return str_replace(' ', '-', $lowered);
    }

    static public function title(string $input) : string {
        // turn any case into title case
        $normalized = self::spaceSeparated($input);
        return ucwords($normalized);
    }

    static private function spaceSeparated(string $input) : string {
        return (new Pipeline)->through([
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

    static public function contains(string $haystack, string $needle, bool $caseSensitive = true) : bool {
        return match($caseSensitive) {
            true => strpos($haystack, $needle) !== false,
            false => stripos($haystack, $needle) !== false,
        };
    }

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

    public static function startsWith(string $url, string $string) : bool {
        return substr($url, 0, strlen($string)) === $string;
    }

    public static function endsWith(string $url, string $string) : bool {
        return substr($url, -strlen($string)) === $string;
    }

    public static function between(mixed $url, string $string, string $string1) : string {
        $start = strpos($url, $string);
        if ($start === false) {
            return '';
        }
        $start += strlen($string);
        $end = strpos($url, $string1, $start);
        if ($end === false) {
            return '';
        }
        return substr($url, $start, $end - $start);
    }

    public static function after(mixed $url, string $string) : string {
        $start = strpos($url, $string);
        if ($start === false) {
            return '';
        }
        $start += strlen($string);
        return substr($url, $start);
    }

    public static function when(bool $condition, string $onTrue, string $onFalse) : string {
        return match($condition) {
            true => $onTrue,
            default => $onFalse,
        };
    }

    public static function limit(
        string $text,
        int    $limit,
        string $cutMarker = '…',
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
