<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** Explicit path boundary; this value never reads process environment itself. */
final readonly class TellResolvedPaths
{
    public function __construct(
        public string $project,
        public string $home,
        public string $configDirectory,
        public string $configFile,
        public string $credentials,
        public string $connections,
        public string $packageAgents,
        public string $userAgents,
        public string $projectAgents,
        public string $runtime,
        public string $sessions,
        public string $logs,
        public string $executionTraces,
        public string $sessionTraces,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array {
        return [
            'project' => $this->project,
            'home' => $this->home,
            'configDirectory' => $this->configDirectory,
            'configFile' => $this->configFile,
            'credentials' => $this->credentials,
            'connections' => $this->connections,
            'packageAgents' => $this->packageAgents,
            'userAgents' => $this->userAgents,
            'projectAgents' => $this->projectAgents,
            'runtime' => $this->runtime,
            'sessions' => $this->sessions,
            'logs' => $this->logs,
            'executionTraces' => $this->executionTraces,
            'sessionTraces' => $this->sessionTraces,
        ];
    }
}
