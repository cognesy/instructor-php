<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

use Cognesy\Tell\Shell\TellShellJobOutput;
use Cognesy\Tell\Shell\TellShellJobRequest;
use Cognesy\Tell\Shell\TellShellJobSnapshot;
use Cognesy\Tell\Shell\TellShellJobState;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Process\Process;

/** @internal Owns one OS process and its bounded output buffer. */
final class TellShellJobProcess
{
    private TellShellJobState $state = TellShellJobState::Running;

    private readonly TellShellJobOutputBuffer $output;

    private ?DateTimeImmutable $finishedAt = null;

    private ?int $exitCode = null;

    private bool $disposed = false;

    private function __construct(
        private readonly string $id,
        private readonly TellShellJobRequest $request,
        private readonly string $workingDirectory,
        private readonly int $timeoutMs,
        private readonly int $cancellationGraceMs,
        private readonly int $maxReadBytes,
        private readonly Process $process,
        private readonly DateTimeImmutable $startedAt,
        private readonly float $startedAtMonotonic,
        int $maxRetainedOutputBytes,
        private readonly bool $ownsProcessGroup,
    ) {
        $this->output = new TellShellJobOutputBuffer($maxRetainedOutputBytes);
    }

    public static function start(
        string $id,
        TellShellJobRequest $request,
        string $workingDirectory,
        int $timeoutMs,
        int $maxRetainedOutputBytes,
        int $maxReadBytes,
        int $cancellationGraceMs,
    ): self {
        $worker = dirname(__DIR__, 2).'/resources/shell-job-worker.php';
        if (! is_file($worker)) {
            throw new RuntimeException('The Tell shell job worker is unavailable.');
        }

        $process = new Process(
            [PHP_BINARY, $worker, $request->command],
            $workingDirectory,
            timeout: null,
        );
        $process->start();

        return new self(
            id: $id,
            request: $request,
            workingDirectory: $workingDirectory,
            timeoutMs: $timeoutMs,
            cancellationGraceMs: $cancellationGraceMs,
            maxReadBytes: $maxReadBytes,
            process: $process,
            startedAt: new DateTimeImmutable,
            startedAtMonotonic: hrtime(true) / 1_000_000,
            maxRetainedOutputBytes: $maxRetainedOutputBytes,
            ownsProcessGroup: DIRECTORY_SEPARATOR === '/'
                && function_exists('posix_kill')
                && function_exists('posix_setsid')
                && function_exists('pcntl_exec'),
        );
    }

    public function snapshot(): TellShellJobSnapshot
    {
        $this->refresh();

        return new TellShellJobSnapshot(
            id: $this->id,
            state: $this->state,
            commandHash: hash('sha256', $this->request->command),
            label: $this->request->label,
            workingDirectory: $this->workingDirectory,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
            exitCode: $this->exitCode,
            stdoutBytes: $this->output->stdoutBytes(),
            stderrBytes: $this->output->stderrBytes(),
            outputTruncated: $this->output->wasTruncated(),
        );
    }

    public function read(int $after): TellShellJobOutput
    {
        $this->refresh();

        return $this->output->read($this->id, $after, $this->maxReadBytes);
    }

    public function refresh(): void
    {
        if ($this->state->isTerminal()) {
            return;
        }

        $running = $this->process->isRunning();
        $this->drainOutput();
        if ($running && $this->elapsedMs() >= $this->timeoutMs) {
            $this->terminate(TellShellJobState::TimedOut);

            return;
        }
        if (! $running) {
            $this->settleFromExitCode();
        }
    }

    public function cancel(): void
    {
        if ($this->state->isTerminal()) {
            return;
        }

        $this->terminate(TellShellJobState::Cancelled);
    }

    public function dispose(): void
    {
        if ($this->disposed) {
            return;
        }
        $this->disposed = true;
        $this->cancel();
    }

    private function terminate(TellShellJobState $terminal): void
    {
        if (! $this->process->isRunning()) {
            $this->drainOutput();
            $this->settleFromExitCode();

            return;
        }

        $pid = $this->process->getPid();
        $sentToGroup = $this->ownsProcessGroup
            && $pid !== null
            && @posix_kill(-$pid, SIGTERM);

        if (! $sentToGroup) {
            try {
                $this->process->signal(SIGTERM);
            } catch (\Throwable) {
                // Symfony's bounded stop below remains the portable fallback.
            }
        }

        $deadline = (hrtime(true) / 1_000_000) + $this->cancellationGraceMs;
        while ($this->process->isRunning() && (hrtime(true) / 1_000_000) < $deadline) {
            $this->drainOutput();
            usleep(10_000);
        }

        if ($this->process->isRunning()) {
            if ($sentToGroup && $pid !== null) {
                @posix_kill(-$pid, SIGKILL);
            } else {
                $this->process->stop(0.0, SIGKILL);
            }
        }
        $this->process->isRunning();
        $this->drainOutput();
        $this->exitCode = $this->process->getExitCode();
        $this->state = $terminal;
        $this->finishedAt = new DateTimeImmutable;
    }

    private function settleFromExitCode(): void
    {
        $this->exitCode = $this->process->getExitCode();
        $this->state = $this->exitCode === 0
            ? TellShellJobState::Exited
            : TellShellJobState::Failed;
        $this->finishedAt = new DateTimeImmutable;
    }

    private function drainOutput(): void
    {
        $this->output->append('stdout', $this->process->getIncrementalOutput());
        $this->output->append('stderr', $this->process->getIncrementalErrorOutput());
    }

    private function elapsedMs(): float
    {
        return (hrtime(true) / 1_000_000) - $this->startedAtMonotonic;
    }
}
