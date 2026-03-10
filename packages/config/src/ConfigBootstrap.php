<?php declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;

final readonly class ConfigBootstrap
{
    /** @return array<string, mixed> */
    public function bootstrap(ConfigFileSet $fileSet): array
    {
        $graph = [];
        foreach ($fileSet->all() as $file) {
            $entry = Config::fromPaths(dirname($file))->load(basename($file));
            $key = $fileSet->keyFor($file);

            if ($this->hasPath($graph, $key)) {
                throw new InvalidArgumentException("Duplicate config key derived from file set: {$key}");
            }

            $graph = $this->withValueAtPath($graph, $key, $entry->toArray());
        }

        return $graph;
    }

    /** @param array<string, mixed> $graph */
    private function hasPath(array $graph, string $key): bool
    {
        $current = $graph;
        foreach (explode('.', $key) as $segment) {
            if (!array_key_exists($segment, $current)) {
                return false;
            }

            $value = $current[$segment];
            if (!is_array($value)) {
                return true;
            }

            $current = $value;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $graph
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function withValueAtPath(array $graph, string $key, array $value): array
    {
        $segments = explode('.', $key);
        $segment = array_shift($segments);

        if ($segment === null || $segment === '') {
            throw new InvalidArgumentException("Invalid config key: {$key}");
        }

        if ($segments === []) {
            $graph[$segment] = $value;

            return $graph;
        }

        $nested = $graph[$segment] ?? [];
        if (!is_array($nested)) {
            throw new InvalidArgumentException("Config key collides with scalar value: {$key}");
        }

        $graph[$segment] = $this->withValueAtPath($nested, implode('.', $segments), $value);

        return $graph;
    }
}
