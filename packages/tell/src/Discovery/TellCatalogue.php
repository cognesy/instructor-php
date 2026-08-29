<?php

declare(strict_types=1);

namespace Cognesy\Tell\Discovery;

use Cognesy\Tell\Runtime\TellAgentFactory;

/** Credential-free, read-only discovery of installed Tell connection presets. */
final readonly class TellCatalogue
{
    public function __construct(
        private TellAgentFactory $agents,
        private string $directory,
    ) {}

    /** @return array{connections: list<array<string, mixed>>, errors: list<array<string, string>>} */
    public function connections(): array {
        return (new TellProviderCatalogue($this->agents->paths()))->connections($this->directory);
    }

    /** @return list<array<string, mixed>> */
    public function models(?string $providerOrConnection = null): array {
        return (new TellProviderCatalogue($this->agents->paths()))->models($this->directory, $providerOrConnection);
    }
}
