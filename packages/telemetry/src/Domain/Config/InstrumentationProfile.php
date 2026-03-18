<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Config;

final readonly class InstrumentationProfile
{
    public function __construct(
        private bool $enabled = true,
        private bool $includeContent = true,
        private bool $includeBinaryContent = false,
    ) {}

    public static function enabled(): self {
        return new self();
    }

    public static function disabled(): self {
        return new self(enabled: false);
    }

    public function enabledFlag(): bool {
        return $this->enabled;
    }

    public function includeContent(): bool {
        return $this->includeContent;
    }

    public function includeBinaryContent(): bool {
        return $this->includeBinaryContent;
    }
}
