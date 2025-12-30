<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Drivers;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Contracts\CanRunProcess;
use Cognesy\Utils\Sandbox\Contracts\CanStreamCommand;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Runners\SymfonyProcessRunner;
use Cognesy\Utils\Sandbox\Utils\EnvUtils;
use Cognesy\Utils\Sandbox\Utils\TimeoutTracker;
use Cognesy\Utils\Sandbox\Utils\Workdir;

final class HostSandbox implements CanStreamCommand
{
    public function __construct(private readonly ExecutionPolicy $policy) {}

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
        $workDir = Workdir::create($this->policy);
        $env = EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars());
        $result = $this->makeProcRunner()->runStreaming(
            argv: $argv,
            cwd: $workDir,
            env: $env,
            stdin: $stdin,
            onOutput: $onOutput,
        );
        Workdir::remove($workDir);
        return $result;
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function makeProcRunner(): CanRunProcess {
        return new SymfonyProcessRunner(
            tracker: new TimeoutTracker(
                wallSeconds: $this->policy->timeoutSeconds(),
                idleSeconds: $this->policy->idleTimeoutSeconds(),
            ),
            stdoutCap: $this->policy->stdoutLimitBytes(),
            stderrCap: $this->policy->stderrLimitBytes(),
            timeoutSeconds: $this->policy->timeoutSeconds(),
            idleSeconds: $this->policy->idleTimeoutSeconds(),
        );
    }
}
