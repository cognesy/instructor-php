<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Agent\Definitions;

use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Contract\Agent\CanLoadTellAgentDefinitions;
use Cognesy\Tell\Core\Discovery\StartupScanCounter;

final readonly class FilesystemTellAgentDefinitions implements CanLoadTellAgentDefinitions
{
    public function __construct(
        private TellPaths $paths,
        private ?StartupScanCounter $startupScans = null,
    ) {}

    #[\Override]
    public function definitions(string $projectPath): AgentDefinitionRegistry {
        $this->startupScans?->recordAgentDefinitionScan();
        $registry = new AgentDefinitionRegistry();
        $registry->autoDiscover(
            projectPath: $projectPath,
            packagePath: $this->paths->packageAgents,
            userPath: $this->paths->userAgents,
        );

        return $registry;
    }
}
