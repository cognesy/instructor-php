<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Drivers;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Contracts\CanRunProcess;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Runners\ProcRunner;
use Cognesy\Utils\Sandbox\Utils\EnvUtils;
use Cognesy\Utils\Sandbox\Utils\ProcUtils;
use Cognesy\Utils\Sandbox\Utils\TimeoutTracker;
use Cognesy\Utils\Sandbox\Utils\Workdir;

final class FirejailSandbox implements CanExecuteCommand
{
    private readonly string $firejailBin;

    public function __construct(
        private readonly ExecutionPolicy $policy,
        ?string $firejailBin = null,
    ) {
        $this->firejailBin = $firejailBin ?? $this->findFirejailPath() ?? 'firejail';
    }

    #[\Override]
    public function policy(): ExecutionPolicy {
        return $this->policy;
    }

    #[\Override]
    public function execute(array $argv, ?string $stdin = null): ExecResult {
        return $this->run($argv, $stdin);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function run(array $argv, ?string $stdin): ExecResult {
        if ($this->firejailBin === '') {
            throw new \RuntimeException('Firejail binary not found');
        }
        $workDir = Workdir::create($this->policy);
        $cmd = $this->buildCommand($workDir, $argv);
        $env = EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars());
        $result = $this->makeProcRunner()->run(
            argv: $this->buildLaunch($cmd),
            cwd: getcwd() ?: $workDir,
            env: $env,
            stdin: $stdin,
        );
        Workdir::remove($workDir);
        return $result;
    }

    private function makeProcRunner(): CanRunProcess {
        return new ProcRunner(
            tracker: new TimeoutTracker(
                wallSeconds: $this->policy->timeoutSeconds(),
                idleSeconds: $this->policy->idleTimeoutSeconds(),
            ),
            stdoutCap: $this->policy->stdoutLimitBytes(),
            stderrCap: $this->policy->stderrLimitBytes(),
            nameForError: 'firejail',
        );
    }

    /** @return list<string> */
    private function buildCommand(string $workDir, array $innerArgv): array {
        $cmd = [
            $this->firejailBin,
            '--noprofile',  // Minimal configuration to debug stdin EBADF
        ];
        if (!$this->policy->networkEnabled()) {
            $cmd[] = '--net=none';
        }
        $cmd = [...$cmd, '--rlimit-nproc=20', '--rlimit-nofile=100'];
        $cmd[] = '--rlimit-fsize=10485760';
        $cmd[] = '--rlimit-cpu=' . (string)($this->policy->timeoutSeconds() + 1);

        $cmd[] = '--bind=' . $workDir . ',/work';
        $cmd[] = '--whitelist=/work';
        $cmd[] = '--chdir=/work';

        $index = 0;
        foreach ($this->policy->writablePaths() as $p) {
            $real = realpath((string)$p) ?: '';
            if ($real === '' || str_contains($real, '..') || str_contains($real, ':')) { continue; }
            $dst = '/mnt/rw' . $index;
            $cmd[] = '--bind=' . $real . ',' . $dst;
            $index++;
        }

        $index = 0;
        foreach ($this->policy->readablePaths() as $p) {
            $real = realpath((string)$p) ?: '';
            if ($real === '' || str_contains($real, '..') || str_contains($real, ':')) { continue; }
            $dst = '/mnt/ro' . $index;
            $cmd[] = '--bind=' . $real . ',' . $dst;
            $cmd[] = '--read-only=' . $dst;
            $index++;
        }

        foreach (EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars()) as $k => $v) {
            $cmd[] = '--env=' . $k . '=' . $v;
        }
        foreach ($innerArgv as $part) {
            $cmd[] = (string)$part;
        }
        return $cmd;
    }

    /** @param list<string> $cmd */
    private function buildLaunch(array $cmd): array {
        $setsid = ProcUtils::findSetSidPath();
        return $setsid ? array_merge([$setsid, '--'], $cmd) : $cmd;
    }

    private function findFirejailPath(): ?string {
        $env = getenv('FIREJAIL_BIN');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }
        $extra = ProcUtils::defaultBinPaths();
        return ProcUtils::findOnPath('firejail', $extra);
    }
}

