<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Drivers;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanRunProcess;
use Cognesy\Utils\Sandbox\Contracts\CanStreamCommand;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Runners\ProcRunner;
use Cognesy\Utils\Sandbox\Utils\ContainerCommandBuilder;
use Cognesy\Utils\Sandbox\Utils\EnvUtils;
use Cognesy\Utils\Sandbox\Utils\ProcUtils;
use Cognesy\Utils\Sandbox\Utils\TimeoutTracker;
use Cognesy\Utils\Sandbox\Utils\Workdir;

final class PodmanSandbox implements CanStreamCommand
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
        return $this->executeStreaming($argv, null, $stdin);
    }

    #[\Override]
    public function executeStreaming(array $argv, ?callable $onOutput, ?string $stdin = null): ExecResult {
        return $this->run($argv, $onOutput, $stdin);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function run(array $argv, ?callable $onOutput, ?string $stdin): ExecResult {
        if ($this->podmanBin === '') {
            throw new \RuntimeException('Podman binary not found');
        }

        $workDir = Workdir::create($this->policy);
        $cmd = $this->buildContainerCommand($workDir, $argv);
        $env = EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars());
        $result = $this->makeProcRunner()->runStreaming(
            argv: $this->buildLaunch($cmd),
            cwd: getcwd() ?: $workDir,
            env: $env,
            stdin: $stdin,
            onOutput: $onOutput,
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
        $isWSL2 = $this->isWSL2Environment();

        $builder = ContainerCommandBuilder::podman($this->podmanBin)
            ->withImage($this->image)
            ->withNetwork($this->policy->networkEnabled())
            ->withPidsLimit(20)
            ->withReadOnlyRoot(true)
            ->withTmpfs('/tmp:rw,noexec,nodev,nosuid,size=64m')
            ->withNoNewPrivileges(true)
            ->withUser('65534:65534')
            ->withWorkdir('/work')
            ->mountWorkdir($workDir)
            ->withEnv(EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars()))
            ->withInnerArgv($argv);

        // Add WSL2 compatibility settings
        if ($isWSL2) {
            $builder
                ->addGlobalFlag('--cgroup-manager=cgroupfs')
                ->withResourceLimits(false); // Skip memory and CPU limits in WSL2
        } else {
            $builder
                ->withMemory($this->policy->memoryLimit())
                ->withCpus('0.5');
        }

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

    private function isWSL2Environment(): bool {
        // Check for WSL2 environment indicators
        if (is_file('/proc/version')) {
            $version = file_get_contents('/proc/version');
            if ($version !== false && (str_contains($version, 'WSL2') || str_contains($version, 'microsoft'))) {
                return true;
            }
        }

        // Check if cgroup is problematic (WSL2 indicator)
        if (is_file('/proc/self/cgroup')) {
            $cgroup = file_get_contents('/proc/self/cgroup');
            if ($cgroup !== false && trim($cgroup) === '0::/') {
                return true;
            }
        }

        return false;
    }
}
