<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Runners;

use Cognesy\Utils\Sandbox\Contracts\CanRunProcess;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Data\ExitCodes;
use Cognesy\Utils\Sandbox\Utils\StreamAggregator;
use Cognesy\Utils\Sandbox\Utils\TimeoutTracker;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SymfonyProcessRunner implements CanRunProcess
{
    public function __construct(
        private readonly TimeoutTracker $tracker,
        private readonly int $stdoutCap,
        private readonly int $stderrCap,
        private readonly int $timeoutSeconds,
        private readonly ?int $idleSeconds = null,
    ) {}

    /**
     * @param list<string> $argv
     * @param array<string,string> $env
     */
    #[\Override]
    public function run(array $argv, string $cwd, array $env, ?string $stdin): ExecResult {
        return $this->runStreaming($argv, $cwd, $env, $stdin, null);
    }

    /**
     * @param list<string> $argv
     * @param array<string,string> $env
     * @param callable(string, string): void|null $onOutput
     */
    #[\Override]
    public function runStreaming(array $argv, string $cwd, array $env, ?string $stdin, ?callable $onOutput): ExecResult {
        $process = $this->makeProcess($argv, $cwd, $env, $stdin);
        $agg = new StreamAggregator(stdoutCap: $this->stdoutCap, stderrCap: $this->stderrCap);
        try {
            $process->run($this->makeProcessCallback($agg, $onOutput));
        } catch (ProcessTimedOutException) {
            return $this->makeFailureResult($agg, $process);
        }
        return $this->makeSuccessResult($agg, $process);
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////

    private function makeProcess(array $argv, string $cwd, array $env, ?string $stdin): Process {
        $this->tracker->start();
        $process = new Process($argv, $cwd, $env, $stdin);
        $process->setTimeout($this->timeoutSeconds);
        if ($this->idleSeconds !== null) {
            $process->setIdleTimeout($this->idleSeconds);
        }
        return $process;
    }

    /**
     * @return callable(string, string): void
     */
    private function makeProcessCallback(StreamAggregator $agg, ?callable $onOutput = null): callable {
        return function (string $type, string $buffer) use ($agg, $onOutput): void {
            if ($buffer !== '') {
                $this->tracker->onActivity();
            }
            $agg->consume($type, $buffer);

            // Call external streaming callback if provided
            if ($onOutput !== null && $buffer !== '') {
                $onOutput($type, $buffer);
            }
        };
    }

    private function makeFailureResult(
        StreamAggregator $agg,
        Process $process,
    ): ExecResult {
        return new ExecResult(
            stdout: $agg->stdout() !== '' ? $agg->stdout() : $process->getOutput(),
            stderr: $agg->stderr() !== '' ? $agg->stderr() : $process->getErrorOutput(),
            exitCode: ExitCodes::TIMEOUT,
            duration: $this->tracker->duration(),
            timedOut: true,
            truncatedStdout: $agg->truncatedStdout(),
            truncatedStderr: $agg->truncatedStderr(),
        );
    }

    private function makeSuccessResult(StreamAggregator $agg, Process $process): ExecResult {
        return new ExecResult(
            stdout: $agg->stdout(),
            stderr: $agg->stderr(),
            exitCode: $process->getExitCode() ?? -1,
            duration: $this->tracker->duration(),
            timedOut: false,
            truncatedStdout: $agg->truncatedStdout(),
            truncatedStderr: $agg->truncatedStderr(),
        );
    }
}
