<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Drivers;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Contracts\CanRunProcess;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Sandbox\Runners\SymfonyProcessRunner;
use Cognesy\Sandbox\Utils\EnvUtils;
use Cognesy\Sandbox\Utils\TimeoutTracker;

final class HostSandbox implements CanExecuteCommand
{
    public function __construct(private readonly ExecutionPolicy $policy) {}

    #[\Override]
    public function policy(): ExecutionPolicy {
        return $this->policy;
    }

    #[\Override]
    public function execute(array $argv, ?string $stdin = null, ?callable $onOutput = null): ExecResult {
        // Run in baseDir directly - no temp directory isolation for host sandbox
        $workDir = $this->policy->baseDir();
        $env = EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars());
        return $this->makeProcRunner()->run(
            argv: $argv,
            cwd: $workDir,
            env: $env,
            stdin: $stdin,
            onOutput: $onOutput,
        );
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
