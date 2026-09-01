<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Paths\Installed;

use Cognesy\Tell\Core\Paths\TellPaths;

use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Data\TellResolvedPaths;

final readonly class StandardTellPathResolver implements CanResolveTellPaths
{
    public function __construct(private TellPaths $paths) {}

    #[\Override]
    public function resolve(string $directory): TellResolvedPaths {
        $project = rtrim($directory, '/\\');

        return new TellResolvedPaths(
            project: $project,
            home: $this->paths->home,
            configDirectory: $this->paths->configDirectory,
            configFile: $this->paths->configFile,
            credentials: $this->paths->credentials,
            connections: $this->paths->connections,
            packageAgents: $this->paths->packageAgents,
            userAgents: $this->paths->userAgents,
            projectAgents: $project . '/.claude/agents',
            runtime: $this->paths->runtime,
            sessions: $this->paths->sessions,
            logs: $this->paths->logs,
            executionTraces: $this->paths->executionTraces,
            sessionTraces: $this->paths->sessionTraces,
        );
    }
}
