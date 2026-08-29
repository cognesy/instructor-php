<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use Cognesy\Tell\Contracts\CanApproveTellShellJobs;
use Cognesy\Tell\Contracts\CanManageTellShellJobs;
use Cognesy\Tell\Data\TellShellJobOutput;
use Cognesy\Tell\Data\TellShellJobRequest;
use Cognesy\Tell\Data\TellShellJobSnapshot;
use Cognesy\Tell\Shell\Exception\TellShellJobDeniedException;
use Cognesy\Tell\Shell\Exception\TellShellJobHostDisposedException;
use Cognesy\Tell\Shell\Exception\TellShellJobNotFoundException;
use Cognesy\Tell\Shell\Exception\TellShellJobStartException;
use CordisPhp\Plugin\PluginDefinition;
use CordisPhp\Runtime\Context;
use CordisPhp\Runtime\FiberState;
use InvalidArgumentException;
use Throwable;

/** @internal */
final class CordisTellShellJobs implements CanManageTellShellJobs
{
    /** @var array<string, TellShellJobRecord> */
    private array $jobs = [];

    private bool $disposed = false;

    public function __construct(
        private readonly Context $context,
        private readonly string $projectDirectory,
        private readonly TellShellJobPolicy $policy,
        private readonly CanApproveTellShellJobs $approval,
        private readonly TellShellJobEventEmitter $events,
    ) {}

    public function start(TellShellJobRequest $request): TellShellJobSnapshot {
        $this->assertActive();
        $this->refreshAll();
        [$workingDirectory, $timeoutMs, $maxOutputBytes] = $this->validate($request);

        $decision = $this->approval->approve($request);
        if (!$decision->allowed) {
            $this->events->emit('shell.job.denied', 'shell.jobs', ['reason' => 'host_policy'], 'denied');
            throw new TellShellJobDeniedException($decision->reason);
        }

        $id = bin2hex(random_bytes(12));
        $process = null;
        $fiber = $this->context->plugin(
            PluginDefinition::fromClosure(function (Context $context) use (
                &$process,
                $id,
                $request,
                $workingDirectory,
                $timeoutMs,
                $maxOutputBytes,
            ): null {
                $process = TellShellJobProcess::start(
                    id: $id,
                    request: $request,
                    workingDirectory: $workingDirectory,
                    timeoutMs: $timeoutMs,
                    maxRetainedOutputBytes: $maxOutputBytes,
                    maxReadBytes: $this->policy->maxReadBytes,
                    cancellationGraceMs: $this->policy->cancellationGraceMs,
                );
                $context->scope()->defer(
                    static function () use ($process): void {
                        $process->dispose();
                    },
                    'shell-job-process',
                );

                return null;
            }),
            label: 'shell.job.' . $id,
        );

        if (!$process instanceof TellShellJobProcess || $fiber->state() !== FiberState::Active) {
            $error = $fiber->error();
            try {
                $fiber->dispose();
            } catch (Throwable) {
                // The startup error remains the primary failure.
            }
            $this->events->emit(
                'shell.job.start_failed',
                'shell.jobs',
                ['error' => $error === null ? 'unknown' : $error::class],
                'failed',
            );
            throw new TellShellJobStartException('The shell job could not be started.', $error);
        }

        $this->jobs[$id] = new TellShellJobRecord($process, $fiber);
        $this->events->emit('shell.job.running', $id, [
            'commandHash' => hash('sha256', $request->command),
            'commandBytes' => strlen($request->command),
            'timeoutMs' => $timeoutMs,
            'maxOutputBytes' => $maxOutputBytes,
        ]);

        return $process->snapshot();
    }

    public function status(string $jobId): TellShellJobSnapshot {
        $this->assertActive();
        $record = $this->record($jobId);
        $this->refresh($jobId, $record);

        return $record->process->snapshot();
    }

    public function read(string $jobId, int $after = 0): TellShellJobOutput {
        $this->assertActive();
        if ($after < 0) {
            throw new InvalidArgumentException('A shell job output cursor cannot be negative.');
        }
        $record = $this->record($jobId);
        $this->refresh($jobId, $record);

        return $record->process->read($after);
    }

    public function wait(string $jobId, int $timeoutMs = 30_000): TellShellJobSnapshot {
        $this->assertActive();
        if ($timeoutMs < 1) {
            throw new InvalidArgumentException('A shell job wait timeout must be positive.');
        }

        $deadline = (hrtime(true) / 1_000_000) + $timeoutMs;
        do {
            $snapshot = $this->status($jobId);
            if ($snapshot->isTerminal()) {
                return $snapshot;
            }
            usleep(10_000);
        } while ((hrtime(true) / 1_000_000) < $deadline);

        return $this->status($jobId);
    }

    public function cancel(string $jobId): TellShellJobSnapshot {
        $this->assertActive();
        $record = $this->record($jobId);
        $record->process->cancel();
        $this->refresh($jobId, $record);

        return $record->process->snapshot();
    }

    public function all(): array {
        $this->assertActive();
        $this->refreshAll();

        return array_values(array_map(
            static fn (TellShellJobRecord $record): TellShellJobSnapshot => $record->process->snapshot(),
            $this->jobs,
        ));
    }

    public function dispose(): void {
        if ($this->disposed) {
            return;
        }

        foreach (array_reverse($this->jobs, true) as $jobId => $record) {
            $record->process->dispose();
            $this->refresh($jobId, $record, disposeFiber: false);
        }
        $this->disposed = true;
    }

    /** @return array{string, int, int} */
    private function validate(TellShellJobRequest $request): array {
        $running = count(array_filter(
            $this->jobs,
            static fn (TellShellJobRecord $record): bool => !$record->process->snapshot()->isTerminal(),
        ));
        if ($running >= $this->policy->maxConcurrentJobs) {
            throw new InvalidArgumentException('The shell job concurrency limit has been reached.');
        }

        $timeoutMs = $request->timeoutMs ?? $this->policy->maxLifetimeMs;
        if ($timeoutMs > $this->policy->maxLifetimeMs) {
            throw new InvalidArgumentException('The requested shell job timeout exceeds host policy.');
        }
        $maxOutputBytes = $request->maxOutputBytes ?? $this->policy->maxRetainedOutputBytes;
        if ($maxOutputBytes > $this->policy->maxRetainedOutputBytes) {
            throw new InvalidArgumentException('The requested shell job output bound exceeds host policy.');
        }

        $requestedDirectory = $request->workingDirectory;
        $candidate = $requestedDirectory === null
            ? $this->projectDirectory
            : ($this->isAbsolutePath($requestedDirectory)
                ? $requestedDirectory
                : $this->projectDirectory . DIRECTORY_SEPARATOR . $requestedDirectory);
        $workingDirectory = realpath($candidate);
        if ($workingDirectory === false || !is_dir($workingDirectory)) {
            throw new InvalidArgumentException('The requested shell job working directory does not exist.');
        }
        if ($workingDirectory !== $this->projectDirectory
            && !str_starts_with($workingDirectory, $this->projectDirectory . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('A shell job working directory must remain inside the project.');
        }

        return [$workingDirectory, $timeoutMs, $maxOutputBytes];
    }

    private function refreshAll(): void {
        foreach ($this->jobs as $jobId => $record) {
            $this->refresh($jobId, $record);
        }
    }

    private function refresh(string $jobId, TellShellJobRecord $record, bool $disposeFiber = true): void {
        $snapshot = $record->process->snapshot();
        if (!$snapshot->isTerminal() || $record->terminalObserved) {
            return;
        }

        $record->terminalObserved = true;
        $this->events->emit(
            'shell.job.' . $snapshot->state->value,
            $jobId,
            [
                'exitCode' => $snapshot->exitCode,
                'stdoutBytes' => $snapshot->stdoutBytes,
                'stderrBytes' => $snapshot->stderrBytes,
                'outputTruncated' => $snapshot->outputTruncated,
            ],
            $snapshot->state->value,
        );
        if ($disposeFiber) {
            $record->fiber->dispose();
        }
    }

    private function record(string $jobId): TellShellJobRecord {
        return $this->jobs[$jobId] ?? throw new TellShellJobNotFoundException($jobId);
    }

    private function assertActive(): void {
        if ($this->disposed) {
            throw new TellShellJobHostDisposedException();
        }
    }

    private function isAbsolutePath(string $path): bool {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
