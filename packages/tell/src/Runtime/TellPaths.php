<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use RuntimeException;

final readonly class TellPaths
{
    public string $configDirectory;

    public string $configFile;

    public string $credentials;

    public string $connections;

    public string $userAgents;

    public string $runtime;

    public string $sessions;

    public string $logs;

    public string $executionTraces;

    public string $sessionTraces;

    public function __construct(
        public string $packageAgents,
        public string $home,
    ) {
        $this->configDirectory = $this->join($home, 'config');
        $this->configFile = $this->join($this->configDirectory, 'tell.json');
        $this->credentials = $this->join($this->configDirectory, 'credentials.env');
        $this->connections = $this->join($this->configDirectory, 'connections');
        $this->userAgents = $this->join($this->configDirectory, 'agents');
        $this->runtime = $this->join($home, 'runtime');
        $this->sessions = $this->join($this->runtime, 'sessions');
        $this->logs = $this->join($home, 'logs');
        $this->executionTraces = $this->join($this->logs, 'executions');
        $this->sessionTraces = $this->join($this->logs, 'sessions');
    }

    /** @param array<string, string>|null $environment */
    public static function installed(?array $environment = null): self
    {
        $environment ??= self::processEnvironment();
        $configured = self::environmentPath($environment, 'TELL_HOME');
        $home = self::environmentPath($environment, 'HOME')
            ?? self::environmentPath($environment, 'USERPROFILE');
        if ($configured === null && $home === null) {
            throw new RuntimeException('HOME or USERPROFILE must be set when TELL_HOME is not configured.');
        }

        $tellHome = match ($configured) {
            null => $home.DIRECTORY_SEPARATOR.'.tell',
            default => $configured,
        };

        return new self(
            packageAgents: dirname(__DIR__, 2).'/resources/agents',
            home: $tellHome,
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'home' => $this->home,
            'config' => $this->configFile,
            'connections' => $this->connections,
            'agents' => $this->userAgents,
            'runtime' => $this->runtime,
            'sessions' => $this->sessions,
            'logs' => $this->logs,
            'executionTraces' => $this->executionTraces,
            'sessionTraces' => $this->sessionTraces,
        ];
    }

    /** @return array<string, string> */
    private static function processEnvironment(): array
    {
        $environment = getenv();
        return is_array($environment) ? $environment : [];
    }

    /** @param array<string, string> $environment */
    private static function environmentPath(array $environment, string $name): ?string
    {
        $value = $environment[$name] ?? null;

        return match (true) {
            $value === null, trim($value) === '' => null,
            default => rtrim($value, '/\\'),
        };
    }

    private function join(string $directory, string $name): string
    {
        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name;
    }
}
