<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Data;

final readonly class Mount
{
    public function __construct(
        private string $host,
        private string $container,
        private string $options,
    ) {}

    public function host(): string {
        return $this->host;
    }

    public function container(): string {
        return $this->container;
    }

    public function options(): string {
        return $this->options;
    }

    public function toVolumeArg(): string {
        return $this->host . ':' . $this->container . ':' . $this->options;
    }
}

