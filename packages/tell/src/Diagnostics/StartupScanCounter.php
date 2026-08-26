<?php

declare(strict_types=1);

namespace Cognesy\Tell\Diagnostics;

/**
 * Opt-in diagnostics for semantic startup discovery passes.
 *
 * This counter is deliberately instance-local: benchmarks and tests can
 * observe one application without introducing process-global runtime state.
 */
final class StartupScanCounter
{
    private int $workspaceDiscoveries = 0;

    private int $agentDefinitionScans = 0;

    private int $composerManifestScans = 0;

    public function recordWorkspaceDiscovery(): void
    {
        $this->workspaceDiscoveries++;
    }

    public function recordAgentDefinitionScan(): void
    {
        $this->agentDefinitionScans++;
    }

    public function recordComposerManifestScan(): void
    {
        $this->composerManifestScans++;
    }

    /** @return array{workspaceDiscoveries: int, agentDefinitionScans: int, composerManifestScans: int} */
    public function snapshot(): array
    {
        return [
            'workspaceDiscoveries' => $this->workspaceDiscoveries,
            'agentDefinitionScans' => $this->agentDefinitionScans,
            'composerManifestScans' => $this->composerManifestScans,
        ];
    }
}
