<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Discovery;

use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;

/** Credential-free, read-only discovery of installed Tell connection presets. */
final readonly class TellCatalogue
{
    public function __construct(
        private CanCatalogueTellProviders $providers,
        private string $directory,
    ) {}

    /** @return array{connections: list<array<string, mixed>>, errors: list<array<string, string>>} */
    public function connections(): array {
        return $this->providers->connections($this->directory);
    }

    /** @return list<array<string, mixed>> */
    public function models(?string $providerOrConnection = null): array {
        return $this->providers->models($this->directory, $providerOrConnection);
    }
}
