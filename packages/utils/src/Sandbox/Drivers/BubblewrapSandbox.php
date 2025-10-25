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

final class BubblewrapSandbox implements CanExecuteCommand
{
    private readonly string $bwrapBin;

    public function __construct(
        private readonly ExecutionPolicy $policy,
        ?string $bwrapBin = null,
    ) {
        $this->bwrapBin = $bwrapBin ?? $this->findBwrapPath() ?? 'bwrap';
    }

    public function policy(): ExecutionPolicy {
        return $this->policy;
    }

    public function execute(array $argv, ?string $stdin = null): ExecResult {
        return $this->run($argv, $stdin);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function run(array $argv, ?string $stdin): ExecResult {
        if (!is_string($this->bwrapBin) || $this->bwrapBin === '') {
            throw new \RuntimeException('bubblewrap not found');
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
            nameForError: 'bwrap',
        );
    }

    /** @return list<string> */
    private function buildCommand(string $workDir, array $innerArgv): array {
        $cmd = [$this->bwrapBin, '--die-with-parent'];
        $cmd = [...$cmd, '--unshare-pid', '--unshare-uts', '--unshare-ipc', '--unshare-cgroup'];
        if (!$this->policy->networkEnabled()) {
            $cmd[] = '--unshare-net';
        }
        $cmd = [...$cmd, '--proc', '/proc'];
        $cmd = [...$cmd, '--dev', '/dev'];

        // Present host root read-only to make system binaries available.
        // Writes are denied unless explicitly re-bound below.
        $cmd = [...$cmd, '--ro-bind', '/', '/'];

        // Bind working directory as writable under /work
        $cmd = [...$cmd, '--bind', $workDir, '/work'];
        $cmd = [...$cmd, '--chdir', '/work'];
        $cmd = [...$cmd, '--tmpfs', '/tmp'];

        // Writable mounts: enable write access to configured paths
        $index = 0;
        foreach ($this->policy->writablePaths() as $p) {
            $real = realpath((string)$p) ?: '';
            if ($real === '' || str_contains($real, '..') || str_contains($real, ':')) { continue; }
            $cmd = [...$cmd, '--bind', $real, $real];
            $index++;
        }

        // Env allowlist
        foreach (EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars()) as $k => $v) {
            $cmd = [...$cmd, '--setenv', (string)$k, (string)$v];
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

    private function findBwrapPath(): ?string {
        $env = getenv('BWRAP_BIN');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }
        $extra = ProcUtils::defaultBinPaths();
        return ProcUtils::findOnPath('bwrap', $extra);
    }
}

