<?php declare(strict_types=1);

namespace Cognesy\Config;

final class Dsn
{
    /**
     * @param array<string, mixed> $params
     */
    private function __construct(
        private array $params = [],
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self {
        return new self($params);
    }

    public static function fromString(?string $dsn): self {
        return new self((new DsnParser())->parseString($dsn ?? ''));
    }

    public static function isDsn(string $dsn): bool {
        return (new DsnParser())->isDsn($dsn);
    }

    public static function ifValid(string $dsn): ?self {
        if (!self::isDsn($dsn)) {
            return null;
        }

        return self::fromString($dsn);
    }

    /**
     * @param string|array<int, string> $excluded
     */
    public function without(string|array $excluded): self {
        $keys = is_array($excluded) ? $excluded : [$excluded];
        $params = $this->params;
        foreach ($keys as $key) {
            unset($params[$key]);
        }
        return new self($params);
    }

    public function hasParam(string $key): bool {
        return self::dotHas($this->params, $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array {
        return $this->params;
    }

    public function param(string $key, mixed $default = null): mixed {
        return self::dotGet($this->params, $key, $default);
    }

    public function intParam(string $key, int $default = 0): int {
        $value = $this->param($key, $default);
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => $default,
        };
    }

    public function stringParam(string $key, string $default = ''): string {
        $value = $this->param($key, $default);
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => (string) $value,
            default => $default,
        };
    }

    public function boolParam(string $key, bool $default = false): bool {
        $value = $this->param($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || !is_scalar($value)) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    public function floatParam(string $key, float $default = 0.0): float {
        $value = $this->param($key, $default);
        return match (true) {
            is_float($value) => $value,
            is_int($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => $default,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return $this->params;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function dotHas(array $data, string $key): bool {
        $sentinel = new \stdClass();
        return self::dotGet($data, $key, $sentinel) !== $sentinel;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function dotGet(array $data, string $key, mixed $default): mixed {
        if ($key === '') {
            return $default;
        }

        $current = $data;
        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }
}
