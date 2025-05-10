<?php

namespace Cognesy\Utils\Dsn;

use Adbar\Dot;

class DSN
{
    private Dot $params;
    private const PARAM_SEPARATOR = ',';
    private const KEYVAL_SEPARATOR = '=';

    public function __construct(string $dsn = '') {
        $this->params = $this->parseString($dsn);
    }

    public static function fromString(string $dsn): self {
        return (new self($dsn));
    }

    public function hasParam(string $key) : bool {
        return $this->params->has($key);
    }

    public function params(): array {
        return $this->params->all();
    }

    public function param(string $key, $default = null) {
        if ($this->params->has($key)) {
            return $this->params->get($key);
        }
        return $default;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    private function parseString(string $dsn) : Dot {
        if (empty($dsn)) return new Dot();

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

        return $dot;
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

    private function isPair($pair) : bool {
        return (strpos($pair, self::KEYVAL_SEPARATOR) !== false);
    }

    private function getPairs(string $dsn) : array {
        return array_map('trim', explode(self::PARAM_SEPARATOR, $dsn));
    }

    private function parsePair(string $pair) : array {
        return array_map('trim', explode(self::KEYVAL_SEPARATOR, $pair, 2));
    }
}
