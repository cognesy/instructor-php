<?php declare(strict_types=1);

namespace Cognesy\Config;

readonly final class ConfigEntry
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        private string $key,
        private string $sourcePath,
        private array $data,
    ) {}

    public function key(): string {
        return $this->key;
    }

    public function sourcePath(): string {
        return $this->sourcePath;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array {
        return $this->data;
    }
}
