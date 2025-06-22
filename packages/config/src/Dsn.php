<?php

namespace Cognesy\Config;

use Adbar\Dot;

class Dsn
{
    private Dot $params;

    private function __construct(array $params = []) {
        $this->params = new Dot($params);
    }

    public static function fromArray(array $params): self {
        return (new self($params));
    }

    public static function fromString(?string $dsn): self {
        if (empty($dsn)) {
            return new self();
        }
        $params = (new DsnParser)->parseString($dsn);
        return (new self($params));
    }

    public static function isDsn(string $dsn): bool {
        return (new DsnParser)->isDsn($dsn);
    }

    public static function ifValid(string $dsn): ?self {
        if (self::isDsn($dsn)) {
            return self::fromString($dsn);
        }
        return null;
    }

    public function without(string|array $excluded) : self {
        $excluded = is_array($excluded) ? $excluded : [$excluded];

        $newParams = $this->params->all();
        foreach ($excluded as $key) {
            unset($newParams[$key]);
        }
        // Create a new instance with the modified parameters
        return new self($newParams);
    }

    public function hasParam(string $key) : bool {
        return $this->params->has($key);
    }

    public function params(): array {
        return $this->params->all();
    }

    public function param(string $key, $default = null) : mixed {
        return $this->params->get($key, $default);
    }

    public function intParam(string $key, int $default = 0) : int {
        return (int) $this->param($key, $default);
    }

    public function stringParam(string $key, string $default = '') : string {
        return (string) $this->param($key, $default);
    }

    public function boolParam(string $key, bool $default = false) : bool {
        return (bool) $this->param($key, $default);
    }

    public function floatParam(string $key, float $default = 0.0) : float {
        return (float) $this->param($key, $default);
    }

    public function toArray() : array {
        return $this->params->all();
    }
}
