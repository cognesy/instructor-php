<?php
namespace Cognesy\Instructor\Partials;

use JsonException;

/**
 * Original author: Greg Hunt
 * Original source: https://github.com/greghunt/partial-json/
 * License: MIT
 */
class JsonParser
{
    private $parsers = [];
    private $lastParseReminding = null;
    private $onExtraToken;

    public function __construct()
    {
        $this->parsers = array_fill_keys([' ', "\r", "\n", "\t"], $this->parseSpace(...));
        $this->parsers['['] = $this->parseArray(...);
        $this->parsers['{'] = $this->parseObject(...);
        $this->parsers['"'] = $this->parseString(...);
        $this->parsers['t'] = $this->parseTrue(...);
        $this->parsers['f'] = $this->parseFalse(...);
        $this->parsers['n'] = $this->parseNull(...);

        foreach (str_split('0123456789.-') as $char) {
            $this->parsers[$char] = $this->parseNumber(...);
        }

        $this->onExtraToken = function ($text, $data, $reminding) {
            echo 'Parsed JSON with extra tokens: ' . json_encode(['text' => $text, 'data' => $data, 'reminding' => $reminding]);
        };
    }

    public function parse($s, bool $associative = true)
    {
        if (strlen($s) >= 1) {
            try {
                return json_decode($s, $associative, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                list($data, $reminding) = $this->parseAny($s, $e);
                $this->lastParseReminding = $reminding;
                if ($this->onExtraToken && $reminding) {
                    call_user_func($this->onExtraToken, $s, $data, $reminding);
                }
                return $data;
            }
        } else {
            return json_decode('{}', $associative);
        }
    }

    private function parseAny($s, $e)
    {
        if (!$s) {
            throw $e;
        }
        $parser = $this->parsers[$s[0]] ?? null;
        if (!$parser) {
            throw $e;
        }
        return $parser($s, $e);
    }

    private function parseSpace($s, $e)
    {
        return $this->parseAny(trim($s), $e);
    }

    private function parseArray($s, $e)
    {
        $s = substr($s, 1);  // skip starting '['
        $acc = [];
        $s = trim($s);
        while ($s) {
            if ($s[0] === ']') {
                $s = substr($s, 1);  // skip ending ']'
                break;
            }
            list($res, $s) = $this->parseAny($s, $e);
            $acc[] = $res;
            $s = trim($s);
            if (strpos($s, ',') === 0) {
                $s = substr($s, 1);
                $s = trim($s);
            }
        }
        return [$acc, $s];
    }

    private function parseObject($s, $e)
    {
        $s = substr($s, 1);  // skip starting '{'
        $acc = [];
        $s = trim($s);
        while ($s) {
            if ($s[0] === '}') {
                $s = substr($s, 1);  // skip ending '}'
                break;
            }
            list($key, $s) = $this->parseAny($s, $e);
            $s = trim($s);

            if (!$s || $s[0] === '}') {
                $acc[$key] = null;
                break;
            }

            if ($s[0] !== ':') {
                throw $e;
            }

            $s = substr($s, 1);  // skip ':'
            $s = trim($s);

            if (!$s || in_array($s[0], [',', '}'])) {
                $acc[$key] = null;
                if (strpos($s, ',') === 0) {
                    $s = substr($s, 1);
                }
                break;
            }

            list($value, $s) = $this->parseAny($s, $e);
            $acc[$key] = $value;
            $s = trim($s);
            if (strpos($s, ',') === 0) {
                $s = substr($s, 1);
                $s = trim($s);
            }
        }
        return [$acc, $s];
    }

    private function parseString($s, $e)
    {
        $end = strpos($s, '"', 1);
        while ($end !== false && $s[$end - 1] === '\\') {  // Handle escaped quotes
            $end = strpos($s, '"', $end + 1);
        }
        if ($end === false) {
            // Return the incomplete string without the opening quote
            return [substr($s, 1), ""];
        }
        $strVal = substr($s, 0, $end + 1);
        $s = substr($s, $end + 1);
        return [json_decode($strVal), $s];
    }

    private function parseNumber($s, $e)
    {
        $i = 0;
        while ($i < strlen($s) && strpos('0123456789.-', $s[$i]) !== false) {
            $i++;
        }
        $numStr = substr($s, 0, $i);
        $s = substr($s, $i);
        if ($numStr == '' || substr($numStr, -1) === '.' || substr($numStr, -1) === '-') {
            // Return the incomplete number as is
            return [$numStr, ""];
        }
        if (strpos($numStr, '.') !== false || strpos($numStr, 'e') !== false || strpos($numStr, 'E') !== false) {
            $num = (float) $numStr;
        } else {
            $num = (int) $numStr;
        }
        return [$num, $s];
    }

    private function parseTrue($s, $e)
    {
        if (substr($s, 0, 4) === 'true') {
            return [true, substr($s, 4)];
        }
        throw $e;
    }

    private function parseFalse($s, $e)
    {
        if (substr($s, 0, 5) === 'false') {
            return [false, substr($s, 5)];
        }
        throw $e;
    }

    private function parseNull($s, $e)
    {
        if (substr($s, 0, 4) === 'null') {
            return [null, substr($s, 4)];
        }
        throw $e;
    }
}