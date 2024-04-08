<?php

namespace Cognesy\Instructor\Utils;

class Json
{
    static public function find(string $text) : string {
        if (empty($text)) {
            return '';
        }
        $firstOpenBracket = strpos($text, '{');
        if ($firstOpenBracket === false) {
            return '';
        }
        $lastCloseBracket = strrpos($text, '}');
        if ($lastCloseBracket === false) {
            return '';
        }
        return substr($text, $firstOpenBracket, $lastCloseBracket - $firstOpenBracket + 1);
    }

    static public function findPartial(string $text) : string {
        if (empty($text)) {
            return '';
        }
        $firstOpenBracket = strpos($text, '{');
        if ($firstOpenBracket === false) {
            return '';
        }
        $lastCloseBracket = strrpos($text, '}') ?: strlen($text) - 1;
        return substr($text, $firstOpenBracket, $lastCloseBracket - $firstOpenBracket + 1);
    }

    static public function fix(string $text) : string {
        return (new JsonParser)->fix($text);
    }

    static public function parse(string $text) : mixed {
        return json_decode($text, true);
    }

    static public function parsePartial(string $text) : mixed {
        return (new JsonParser)->parse($text);
    }

    public static function encode(mixed $json, int $options = 0) : string {
        return json_encode($json, $options);
    }
}