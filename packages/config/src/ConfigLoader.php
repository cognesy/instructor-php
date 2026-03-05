<?php declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;

final class ConfigLoader
{
    private function __construct(private readonly Config $config) {}

    public static function fromPaths(string ...$paths): self {
        return new self(config: Config::fromPaths(...$paths));
    }

    public function withCache(string $cachePath): self {
        return new self(config: $this->config->withCache($cachePath));
    }

    public function load(string $oneConfig): ConfigEntry {
        return $this->config->load($oneConfig);
    }

    /**
     * @return array<string, ConfigEntry>
     */
    public function loadAll(string ...$manyConfigs): array {
        if ($manyConfigs === []) {
            throw new InvalidArgumentException('ConfigLoader::loadAll() requires at least one config path');
        }

        $loaded = [];
        foreach ($manyConfigs as $configPath) {
            if (array_key_exists($configPath, $loaded)) {
                continue;
            }
            $loaded[$configPath] = $this->config->load($configPath);
        }

        return $loaded;
    }
}
