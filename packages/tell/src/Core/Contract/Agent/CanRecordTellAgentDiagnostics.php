<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Agent;

use Cognesy\Agents\Discovery\DiscoveryResult;
use Closure;

/** Records diagnostics produced while an agent implementation is assembled. */
interface CanRecordTellAgentDiagnostics
{
    public function recordExtensionDiscovery(DiscoveryResult $result): void;

    /** @param Closure(): list<string> $warnings */
    public function trackWarnings(Closure $warnings): void;
}
