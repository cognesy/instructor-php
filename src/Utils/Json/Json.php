<?php

namespace Cognesy\Instructor\Utils\Json;

class Json
{
    public static function find(string $text) : string {
        if (empty($text)) {
            return '';
        }
        return (new Json)->tryExtractJson($text);
    }

    public static function findPartial(string $text) : string {
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

    public static function fix(string $text) : string {
        return (new PartialJsonParser)->fix($text);
    }

    public static function parse(string $text, mixed $default = null) : mixed {
        if (empty($text)) {
            return $default;
        }
        $decoded = json_decode($text, true);
        return empty($decoded) ? $default : $decoded;
    }

    public static function parsePartial(string $text, bool $associative = true) : mixed {
        return (new PartialJsonParser)->parse($text, $associative);
    }

    public static function encode(mixed $json, int $options = 0) : string {
        return json_encode($json, $options);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function tryExtractJson(string $text) : string {
        // approach 1
        $candidates = $this->extractJSONStrings($text);
        $json = empty($candidates) ? '' : $candidates[0] ?? '';
        if (!empty($json)) {
            return $json;
        }
        // approach 2
        $maybeJson = $this->naiveExtract($text);
        $json = (new ResilientJsonParser($maybeJson))->parse();
        if (!empty($json)) {
            return json_encode($json);
        }
        // failed to find JSON
        return '';
    }

    private function naiveExtract(string $text) : string {
        if (empty($text)) {
            return '';
        }
        if (($firstOpenBracket = strpos($text, '{')) === false) {
            return '';
        }
        if (($lastCloseBracket = strrpos($text, '}')) === false) {
            return '';
        }
        return substr($text, $firstOpenBracket, $lastCloseBracket - $firstOpenBracket + 1);
    }

    private function extractJSONStrings(string $text): array {
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
                    if ($bracketCount === 0) {
                        $currentCandidate .= $char;
                        $candidates[] = $currentCandidate;
                        continue;
                    }
                }
            }

            if ($char === '"' && !$escape) {
                $inString = !$inString;
            }

            $escape = ($char === '\\' && !$escape);

            if ($bracketCount > 0) {
                $currentCandidate .= $char;
            }
        }
        return $this->validateJSONStrings($candidates);
    }

    private function validateJSONStrings(array $candidates): array {
        return array_filter($candidates, function($candidate) {
            json_decode($candidate);
            return json_last_error() === JSON_ERROR_NONE;
        });
    }
}