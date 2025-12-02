<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Config;

/**
 * Beads Configuration
 *
 * Provides type-safe access to beads configuration settings.
 */
final readonly class BeadsConfig
{
    /**
     * @param  array<mixed>  $config
     */
    public function __construct(
        private array $config,
    ) {}

    /**
     * Create from Laravel config array
     *
     * @param  array<mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    /**
     * Get bd binary path (null = auto-detect)
     */
    public function bdBinary(): ?string
    {
        $path = $this->config['binaries']['bd'] ?? null;

        return is_string($path) ? $path : null;
    }

    /**
     * Get bv binary path (null = auto-detect)
     */
    public function bvBinary(): ?string
    {
        $path = $this->config['binaries']['bv'] ?? null;

        return is_string($path) ? $path : null;
    }

    /**
     * Get execution timeout in seconds
     */
    public function timeoutSeconds(): int
    {
        return (int) ($this->config['execution']['timeout_seconds'] ?? 30);
    }

    /**
     * Get stdout limit in MB
     */
    public function stdoutLimitMB(): int
    {
        return (int) ($this->config['execution']['stdout_limit_mb'] ?? 10);
    }

    /**
     * Get stderr limit in MB
     */
    public function stderrLimitMB(): int
    {
        return (int) ($this->config['execution']['stderr_limit_mb'] ?? 1);
    }

    /**
     * Check if network is enabled
     */
    public function networkEnabled(): bool
    {
        return (bool) ($this->config['execution']['network_enabled'] ?? true);
    }

    /**
     * Get max retry attempts
     */
    public function maxRetries(): int
    {
        return (int) ($this->config['retry']['max_attempts'] ?? 0);
    }

    /**
     * Get project base directory
     */
    public function baseDir(): string
    {
        return (string) ($this->config['project']['base_dir'] ?? getcwd());
    }

    /**
     * Get beads directory (.beads/)
     */
    public function beadsDir(): string
    {
        return (string) ($this->config['project']['beads_dir'] ?? getcwd().'/.beads');
    }
}
