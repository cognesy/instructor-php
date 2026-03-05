<?php declare(strict_types=1);

namespace Cognesy\Config;

final class DsnParser
{
    private const PARAM_SEPARATOR = ',';
    private const KEY_VALUE_SEPARATOR = '=';

    public function isDsn(string $dsn): bool {
        return str_contains($dsn, self::KEY_VALUE_SEPARATOR);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseString(string $dsn): array {
        if ($dsn === '') {
            return [];
        }

        $data = [];
        $pairs = $this->pairs($dsn);
        foreach ($pairs as $pair) {
            if (!$this->isPair($pair)) {
                continue;
            }

            [$key, $value] = $this->parsePair($pair);
            if ($key === '') {
                continue;
            }

            $this->setNested($data, $key, $value);
        }

        return $data;
    }

    private function isPair(string $pair): bool {
        return str_contains($pair, self::KEY_VALUE_SEPARATOR);
    }

    /**
     * @return array<int, string>
     */
    private function pairs(string $dsn): array {
        return array_map('trim', explode(self::PARAM_SEPARATOR, $dsn));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parsePair(string $pair): array {
        $parts = array_map('trim', explode(self::KEY_VALUE_SEPARATOR, $pair, 2));
        $key = $parts[0] ?? '';
        $value = $parts[1] ?? '';
        return [$key, $value];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setNested(array &$data, string $key, string $value): void {
        $segments = explode('.', $key);
        $this->setNestedValue($data, $segments, $value);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $segments
     */
    private function setNestedValue(array &$data, array $segments, string $value): void {
        if ($segments === []) {
            return;
        }

        $segment = $segments[0];
        if ($segment === '') {
            return;
        }
        $remainingSegments = array_slice($segments, 1);

        if ($remainingSegments === []) {
            $data[$segment] = $value;
            return;
        }

        $next = $data[$segment] ?? [];
        if (!is_array($next)) {
            $next = [];
        }

        $this->setNestedValue($next, $remainingSegments, $value);
        $data[$segment] = $next;
    }
}
