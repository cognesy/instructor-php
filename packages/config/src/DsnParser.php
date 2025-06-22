<?php

namespace Cognesy\Config;

use Adbar\Dot;

class DsnParser
{
    private const PARAM_SEPARATOR = ',';
    private const KEYVAL_SEPARATOR = '=';

    public function isDsn(string $dsn) : bool {
        return strpos($dsn, self::KEYVAL_SEPARATOR) !== false;
    }

    public function parseString(string $dsn) : array {
        if (empty($dsn)) return [];

        $dot = new Dot();

        $dsn = $this->replaceTemplateVars($dsn, getenv());

        $pairs = $this->getPairs($dsn);
        foreach ($pairs as $pair) {
            if (!$this->isPair($pair)) {
                continue;
            }
            [$key, $value] = $this->parsePair($pair);
            $dot->set($key, $value);
        }

        return $dot->all();
    }

    private function replaceTemplateVars(string $value, array $context) : string {
        $placeholders = [];
        $replacements = [];
        foreach ($context as $key => $val) {
            // Format: {key}
            $placeholders[] = '{' . $key . '}';
            // Ensure replacement is a string
            $replacements[] = is_scalar($val)
                ? (string) $val
                : json_encode($val);
        }
        return str_replace($placeholders, $replacements, $value);
    }

    private function isPair(string $pair) : bool {
        return (strpos($pair, self::KEYVAL_SEPARATOR) !== false);
    }

    private function getPairs(string $dsn) : array {
        return array_map('trim', explode(self::PARAM_SEPARATOR, $dsn));
    }

    private function parsePair(string $pair) : array {
        return array_map('trim', explode(self::KEYVAL_SEPARATOR, $pair, 2));
    }
}