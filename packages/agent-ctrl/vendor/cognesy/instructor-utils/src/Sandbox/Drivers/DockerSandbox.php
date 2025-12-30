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

final class DockerSandbox implements CanStreamCommand
{
    private readonly string $dockerBin;

    public function __construct(
        private readonly ExecutionPolicy $policy,
        private readonly string $image = 'alpine:3',
        ?string $dockerBin = null,
    ) {
        $this->dockerBin = $dockerBin ?? $this->findDockerPath() ?? 'docker';
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
        if ($this->dockerBin === '') {
            throw new \RuntimeException('Docker binary not found');
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
            nameForError: 'docker',
        );
    }

    private function buildContainerCommand(string $workDir, array $argv): array {
        $builder = ContainerCommandBuilder::docker($this->dockerBin)
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

    private function findDockerPath(): ?string {
        $env = getenv('DOCKER_BIN');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }
        $extra = ProcUtils::defaultBinPaths();
        return ProcUtils::findOnPath('docker', $extra);
    }
}
