<?php

declare(strict_types=1);

namespace Cognesy\Tell\Diagnostics;

use Cognesy\Agents\Discovery\DiscoveryResult;

/** Request-local diagnostics collected while constructing and running Tell. */
final class TellDiagnostics
{
    /** @var array<string, TellDiagnostic> */
    private array $diagnostics = [];

    public function recordExtensionDiscovery(DiscoveryResult $result): void
    {
        foreach ($result->errors()->all() as $message) {
            $diagnostic = new TellDiagnostic(
                code: 'extension_discovery_error',
                source: 'composer',
                severity: 'warning',
                message: $message,
            );
            $this->diagnostics[$diagnostic->code."\0".$diagnostic->message] = $diagnostic;
        }
    }

    /** @return list<TellDiagnostic> */
    public function all(): array
    {
        return array_values($this->diagnostics);
    }
}
