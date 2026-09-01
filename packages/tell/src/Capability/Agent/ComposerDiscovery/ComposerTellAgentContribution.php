<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Agent\ComposerDiscovery;

use Cognesy\Agents\Discovery\CapabilityDiscovery;
use Cognesy\Tell\Core\Agent\TellAgentAssembly;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Core\Discovery\StartupScanCounter;

final readonly class ComposerTellAgentContribution implements CanContributeTellAgent
{
    public function __construct(
        private ?StartupScanCounter $startupScans = null,
        private ?string $vendorDirectory = null,
        private ?string $rootComposerPath = null,
    ) {}

    #[\Override]
    public function contribute(TellAgentAssembly $assembly): void {
        $this->startupScans?->recordComposerManifestScan();
        $result = CapabilityDiscovery::discover(
            $assembly->capabilities,
            $assembly->tools,
            $this->vendorDirectory,
            $this->rootComposerPath,
        );
        $assembly->diagnostics?->recordExtensionDiscovery($result);
    }
}
