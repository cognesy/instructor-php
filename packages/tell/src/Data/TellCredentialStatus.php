<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** Secret-free credential provenance exposed across management boundaries. */
final readonly class TellCredentialStatus
{
    public function __construct(
        public string $variable,
        public string $source,
    ) {}

    /** @return array{variable: string, configured: true, source: string} */
    public function toArray(): array {
        return [
            'variable' => $this->variable,
            'configured' => true,
            'source' => $this->source,
        ];
    }
}
