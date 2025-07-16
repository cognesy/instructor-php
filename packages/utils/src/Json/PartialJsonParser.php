<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

use Exception;
use JsonException;
use Throwable;

/**
 * Original author: Greg Hunt
 * Original source: https://github.com/greghunt/partial-json/
 * License: MIT
 */
class PartialJsonParser
{
    private $parsers = [];
    private string $lastParseReminding = '';
    private $onExtraToken;
    private $skipExtraTokens = true;

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
            // we're just skipping extra tokens

            //$message = 'Parsed JSON with extra tokens: ' . Json::encode(['text' => $text, 'data' => $data, 'reminding' => $reminding]);
            //throw new JSONParsingException($message, $text);
        };
    }

    public function fix(string $partialJson) : string {
        return Json::encode($this->parse($partialJson));
    }

    public function parse(string $json, bool $associative = true) : array|object
    {
        if (strlen($json) >= 1) {
            try {
                return json_decode($json, $associative, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                [$data, $reminding] = $this->parseAny($json, $e);
                $this->lastParseReminding = $reminding;
                if ($this->onExtraToken && $reminding && !$this->skipExtraTokens) {
                    ($this->onExtraToken)($json, $data, $reminding);
                }
                return match(true) {
                    is_string($data) => [],
                    default => $data,
                };
            } catch (Exception $e) {
                throw new JsonParsingException('Unexpected error: ' . $e->getMessage());
            }
        } else {
            return json_decode('{}', $associative);
        }
    }

    public function reminder() : string {
        return $this->lastParseReminding;
    }

    private function parseAny(string $json, Throwable $e) : array|object {
        if (!$json) {
            throw $e;
        }
        $parser = $this->parsers[$json[0]] ?? null;
        if (!$parser) {
            throw $e;
        }
        return $parser($json, $e);
    }

    private function parseSpace(string $json, Throwable $e) : array|object {
        return $this->parseAny(trim($json), $e);
    }

    private function parseArray(string $json, Throwable $e) : array {
        $json = substr($json, 1);  // skip starting '['
        $acc = [];
        $json = trim($json);
        while ($json) {
            if ($json[0] === ']') {
                $json = substr($json, 1);  // skip ending ']'
                break;
            }
            [$res, $json] = $this->parseAny($json, $e);
            $acc[] = $res;
            $json = trim($json);
            if (strpos($json, ',') === 0) {
                $json = substr($json, 1);
                $json = trim($json);
            }
        }
        return [$acc, $json];
    }

    private function parseObject(string $json, Throwable $e) : array {
        $json = substr($json, 1);  // skip starting '{'
        $acc = [];
        $json = trim($json);
        while ($json) {
            if ($json[0] === '}') {
                $json = substr($json, 1);  // skip ending '}'
                break;
            }
            [$key, $json] = $this->parseAny($json, $e);
            $json = trim($json);

            if (!$json || $json[0] === '}') {
                $acc[$key] = null;
                break;
            }

            if ($json[0] !== ':') {
                throw $e;
            }

            $json = substr($json, 1);  // skip ':'
            $json = trim($json);

            if (!$json || in_array($json[0], [',', '}'])) {
                $acc[$key] = null;
                if (strpos($json, ',') === 0) {
                    $json = substr($json, 1);
                }
                break;
            }

            [$value, $json] = $this->parseAny($json, $e);
            $acc[$key] = $value;
            $json = trim($json);
            if (strpos($json, ',') === 0) {
                $json = substr($json, 1);
                $json = trim($json);
            }
        }
        return [$acc, $json];
    }

    private function parseString(string $json, Throwable $e) : array {
        $end = strpos($json, '"', 1);
        while ($end !== false && $json[$end - 1] === '\\') {  // Handle escaped quotes
            $end = strpos($json, '"', $end + 1);
        }
        if ($end === false) {
            // Return the incomplete string without the opening quote
            return [substr($json, 1), ""];
        }
        $strVal = substr($json, 0, $end + 1);
        $json = substr($json, $end + 1);
        return [json_decode($strVal), $json];
    }

    private function parseNumber(string $json, Throwable $e) : array {
        $i = 0;
        while ($i < strlen($json) && strpos('0123456789.-', $json[$i]) !== false) {
            $i++;
        }
        $numStr = substr($json, 0, $i);
        $json = substr($json, $i);
        if ($numStr == '' || substr($numStr, -1) === '.' || substr($numStr, -1) === '-') {
            // Return the incomplete number as is
            return [$numStr, ""];
        }
        if (strpos($numStr, '.') !== false || strpos($numStr, 'e') !== false || strpos($numStr, 'E') !== false) {
            $num = (float) $numStr;
        } else {
            $num = (int) $numStr;
        }
        return [$num, $json];
    }

    private function parseTrue(string $json, Throwable $e) : array {
        if (substr($json, 0, 4) === 'true') {
            return [true, substr($json, 4)];
        }
        throw $e;
    }

    private function parseFalse(string $json, Throwable $e) : array {
        if (substr($json, 0, 5) === 'false') {
            return [false, substr($json, 5)];
        }
        throw $e;
    }

    private function parseNull(string $json, Throwable $e) : array {
        if (substr($json, 0, 4) === 'null') {
            return [null, substr($json, 4)];
        }
        throw $e;
    }
}