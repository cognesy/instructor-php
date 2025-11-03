<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Drivers;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Contracts\CanRunProcess;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Runners\ProcRunner;
use Cognesy\Utils\Sandbox\Utils\ContainerCommandBuilder;
use Cognesy\Utils\Sandbox\Utils\EnvUtils;
use Cognesy\Utils\Sandbox\Utils\ProcUtils;
use Cognesy\Utils\Sandbox\Utils\TimeoutTracker;
use Cognesy\Utils\Sandbox\Utils\Workdir;

final class PodmanSandbox implements CanExecuteCommand
{
    private readonly string $podmanBin;

    public function __construct(
        private readonly ExecutionPolicy $policy,
        private readonly string $image = 'alpine:3',
        ?string $podmanBin = null,
    ) {
        $this->podmanBin = $podmanBin ?? $this->findPodmanPath() ?? 'podman';
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
        if ($this->podmanBin === '') {
            throw new \RuntimeException('Podman binary not found');
        }

        $workDir = Workdir::create($this->policy);
        $cmd = $this->buildContainerCommand($workDir, $argv);
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
            nameForError: 'podman',
        );
    }

    private function buildContainerCommand(string $workDir, array $argv): array {
        $builder = ContainerCommandBuilder::podman($this->podmanBin)
            ->withImage($this->image)
            ->withNetwork($this->policy->networkEnabled())
            ->withPidsLimit(20)
            ->withMemory($this->policy->memoryLimit())
            ->withCpus('0.5')
            ->withReadOnlyRoot(true)
            ->withTmpfs('/tmp:rw,noexec,nodev,nosuid,size=64m')
            ->withNoNewPrivileges(true)
            ->withUser('65534:65534')
            ->withWorkdir('/work')
            ->mountWorkdir($workDir)
            ->withEnv(EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars()))
            ->withInnerArgv($argv);

        $index = 0;
        foreach ($this->policy->writablePaths() as $p) {
            $real = realpath($p) ?: '';
            if ($real === '' || str_contains($real, '..') || str_contains($real, ':')) {
                continue;
            }
            $builder->addWritableMount($real, '/mnt/rw' . $index);
            $index++;
        }

        $index = 0;
        foreach ($this->policy->readablePaths() as $p) {
            $real = realpath($p) ?: '';
            if ($real === '' || str_contains($real, '..') || str_contains($real, ':')) {
                continue;
            }
            $builder->addReadonlyMount($real, '/mnt/ro' . $index);
            $index++;
        }
        return $builder->build();
    }

    private function buildLaunch(array $cmd): array {
        $setsid = ProcUtils::findSetSidPath();
        return $setsid ? array_merge([$setsid, '--'], $cmd) : $cmd;
    }

    private function findPodmanPath(): ?string {
        $env = getenv('PODMAN_BIN');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }
        $extra = ProcUtils::defaultBinPaths();
        return ProcUtils::findOnPath('podman', $extra);
    }
}
