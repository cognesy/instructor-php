<?php

namespace Cognesy\Instructor\Utils\Json;

use Exception;

class JsonParser
{
    public function findCompleteJson(string $input) : string {
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

    public function findPartialJson(string $input) : string {
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

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function tryParse(string $maybeJson) : ?array {
        $parsers = [
            fn($json) => json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR),
            fn($json) => (new PartialJsonParser)->parse($json),
            fn($json) => (new ResilientJsonParser($json))->parse(),
        ];

        foreach ($parsers as $parser) {
            try {
                $data = $parser($maybeJson);
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
}