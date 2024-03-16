<?php

namespace Cognesy\InstructorHub\Utils;

class Str
{
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
        // turn camel, snake, or kebab case into a normalized form - space separated
        $decamelized = preg_replace('/(?<!^)[A-Z]/', ' $0', $input);
        $dekebabed = str_replace('-', ' ', $decamelized);
        $desnaked = str_replace('_', ' ', $dekebabed);
        return $desnaked;
    }

    static public function contains(string $haystack, string $needle) : bool {
        return strpos($haystack, $needle) !== false;
    }
}