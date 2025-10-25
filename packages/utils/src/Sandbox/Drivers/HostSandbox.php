<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Drivers;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Contracts\CanRunProcess;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Runners\SymfonyProcessRunner;
use Cognesy\Utils\Sandbox\Utils\EnvUtils;
use Cognesy\Utils\Sandbox\Utils\TimeoutTracker;
use Cognesy\Utils\Sandbox\Utils\Workdir;

final class HostSandbox implements CanExecuteCommand
{
    public function __construct(private readonly ExecutionPolicy $policy) {}

    public function policy(): ExecutionPolicy {
        return $this->policy;
    }

    public function execute(array $argv, ?string $stdin = null): ExecResult {
        return $this->tryExecute($argv, $stdin);
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function tryExecute(array $argv, ?string $stdin): ExecResult {
        $workDir = Workdir::create($this->policy);
        $env = EnvUtils::build($this->policy, EnvUtils::forbiddenEnvVars());
        $result = $this->makeProcRunner()->run(
            argv: $argv,
            cwd: $workDir,
            env: $env,
            stdin: $stdin,
        );
        Workdir::remove($workDir);
        return $result;
    }

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
