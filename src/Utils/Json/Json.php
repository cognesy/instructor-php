<?php

namespace Cognesy\Instructor\Utils\Json;

use Exception;

class Json
{
    private string $json;

    public function __construct(string $json = '') {
        $this->json = $json;
    }

    // NEW API ////////////////////////////////////////////////

    public static function from(string $text) : Json {
        if (empty(trim($text))) {
            return new Json('');
        }
        $instance = new Json;
        $json = $instance->findCompleteJson($text);
        return new Json($json);
    }

    public static function fromPartial(string $text) : Json {
        if (empty(trim($text))) {
            return new Json('');
        }
        $instance = new Json;
        $json = $instance->findPartialJson($text);
        return new Json($json);
    }

    public function isEmpty() : bool {
        return $this->json === '';
    }

    public function toString() : string {
        return $this->json;
    }

    public function toArray() : array {
        if ($this->isEmpty()) {
            return [];
        }
        return json_decode($this->json, true);
    }

    // STATIC /////////////////////////////////////////////////

    public static function parse(string $text, mixed $default = null) : mixed {
        if (empty($text)) {
            return $default;
        }
        $decoded = json_decode($text, true);
        return empty($decoded) ? $default : $decoded;
    }

    public static function encode(mixed $json, int $options = 0) : string {
        return json_encode($json, $options);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function findCompleteJson(string $input) : string {
        $extractors = [
            fn($text) => $text,
            fn($text) => $this->findByMarkdown($text),
            fn($text) => $this->findByBrackets($text),
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
                $data = $this->tryParse($candidate);
                if ($data !== null) {
                    $result = json_encode($data);
                    if ($result !== false) {
                        return $result;
                    }
                }
            }
        }
        return '';
    }

    private function findPartialJson(string $input) : string {
        $extractors = [
            fn($text) => $text,
            fn($text) => $this->findPartialByMarkdown($text),
            fn($text) => $this->findPartialByBrackets($text),
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
                $data = $this->tryParse($candidate);
                if ($data !== null) {
                    return json_encode($data);
                }
            }
        }
        return '';
    }

    private function tryParse(string $json) : ?array {
        $parsers = [
            fn($json) => json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR),
            fn($json) => (new PartialJsonParser)->parse($json),
            fn($json) => (new ResilientJsonParser($json))->parse(),
        ];
        foreach ($parsers as $parser) {
            try {
                $data = $parser($json);
            } catch(Exception $e) {
                continue;
            }
            if ($data === false || $data === null || $data === '') {
                continue;
            }
            return $data;
        }
        return null;
    }

    private static function findPartialByMarkdown(string $text) : string {
        $firstOpenBracket = strpos($text, '```json');
        if ($firstOpenBracket === false) {
            return '';
        }
        $lastCloseBracket = strrpos($text, '```') ?: strlen($text) - 1;
        if ($lastCloseBracket === false) {
            return '';
        }
        $firstOpenBracket = strpos($text, '{', $firstOpenBracket);
        if ($firstOpenBracket === false || $firstOpenBracket > $lastCloseBracket) {
            return '';
        }
        $lastCloseBracket = strrpos($text, '}', $lastCloseBracket);
        if ($lastCloseBracket === false || $lastCloseBracket < $firstOpenBracket) {
            return '';
        }
        return substr($text, $firstOpenBracket, $lastCloseBracket - $firstOpenBracket + 1);
    }

    private static function findPartialByBrackets(string $text) : string {
        $firstOpenBracket = strpos($text, '{');
        if ($firstOpenBracket === false) {
            return '';
        }
        $lastCloseBracket = strrpos($text, '}') ?: strlen($text) - 1;
        return substr($text, $firstOpenBracket, $lastCloseBracket - $firstOpenBracket + 1);
    }

    private function findByBrackets(string $text) : string {
        if (empty(trim($text))) {
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

    private function findByMarkdown(string $text) : string {
        if (empty(trim($text))) {
            return '';
        }
        if (($firstOpening = strpos($text, '```json')) === false) {
            return '';
        }
        if (($lastClosing = strpos($text, '```', $firstOpening + 7)) === false) {
            return '';
        }
        $firstOpening = strpos($text, '{', $firstOpening);
        $lastClosing = strrpos($text, '}', $lastClosing);
        if ($firstOpening === false || $lastClosing === false || $firstOpening > $lastClosing) {
            return '';
        }
        return substr($text, $firstOpening, $lastClosing - $firstOpening + 1);
    }

    private function findJSONLikeStrings(string $text): array {
        if (empty(trim($text))) {
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
        return $candidates;
    }

    private function filterInvalidCandidates(array $candidates) : array {
        return array_filter($candidates, function($candidate) {
            $temp = json_decode($candidate);
            return json_last_error() === JSON_ERROR_NONE;
        });
    }
}

//public static function find(string $text) : string {
//    if (empty($text)) {
//        return '';
//    }
//    return (new Json)->tryExtractJson($text);
//}
//
//public static function findPartial(string $text) : string {
//    if (empty($text)) {
//        return '';
//    }
//    return self::findPartialByBrackets($text);
//}
//
//public static function findAndFixPartial(string $partialJson) : string {
//    if ($partialJson === '') {
//        return '';
//    }
//    $maybeJson = self::findPartial($partialJson);
//    return (new PartialJsonParser)->fix($maybeJson);
//}
//
//public static function parsePartial(string $text, bool $associative = true) : mixed {
//    return (new PartialJsonParser)->parse($text, $associative);
//}
//
//private function tryExtractJson(string $text) : string {
//    // approach 1
//    $maybeJson = $this->findByBrackets($text);
//    $json = (new ResilientJsonParser($maybeJson))->parse();
//    if (!empty($json)) {
//        return json_encode($json);
//    }
//    // approach 2
//    $candidates = $this->findJSONLikeStrings($text);
//    $candidates = $this->filterInvalidCandidates($candidates);
//    $json = empty($candidates) ? '' : $candidates[0] ?? '';
//    if (!empty($json)) {
//        return $json;
//    }
//    // failed to find JSON
//    return '';
//}

