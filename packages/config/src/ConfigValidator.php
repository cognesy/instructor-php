<?php declare(strict_types=1);

namespace Cognesy\Config;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

final readonly class ConfigValidator
{
    public function __construct(
        private mixed $configuration = null,
    ) {}

    /** @param array<string, mixed> $config */
    public function validate(array $config): array
    {
        return match (true) {
            $this->configuration === null => $config,
            $this->configuration instanceof ConfigurationInterface => (new Processor())->processConfiguration($this->configuration, [$config]),
            is_callable($this->configuration) => ($this->configuration)($config),
            default => $config,
        };
    }
}
