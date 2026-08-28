<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Tell\Diagnostics\TellDiagnostic;
use Cognesy\Tell\Diagnostics\TellDiagnostics;
use Cognesy\Tell\TellProgress;
use Cognesy\Tell\TellResult;
use Generator;
use RuntimeException;
use Throwable;

/**
 * A handle over one Tell run. The outcome lives here rather than behind the
 * stream's `return`, so a caller that stops iterating early still gets its
 * result instead of "Cannot get return value of a generator that hasn't
 * returned", and a run torn down before it commits says so.
 */
final class TellRun
{
    private bool $started = false;

    /**
     * @param Generator<int, TellProgress, mixed, TellResult> $stream
     */
    public function __construct(
        private readonly Generator $stream,
        private readonly TellRunOutcome $outcome,
        private readonly TellDiagnostics $diagnostics,
    ) {}

    /**
     * Progress checkpoints for this run. Abandoning this generator is allowed;
     * it is recorded rather than passed over in silence.
     *
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    public function checkpoints(): Generator
    {
        $this->started = true;
        try {
            $result = yield from $this->stream;
            $this->outcome->recordResult($result);

            return $result;
        } finally {
            $this->noteIfAbandoned();
        }
    }

    /** True once the run reached its terminal outcome and applied its effects. */
    public function isCommitted(): bool
    {
        return $this->outcome->isCommitted();
    }

    /**
     * The run's result. Available as soon as the run commits, whether or not the
     * caller drained the checkpoints.
     */
    public function result(): TellResult
    {
        $result = $this->outcome->result();
        if ($result !== null) {
            return $result;
        }
        throw new RuntimeException(
            'Tell run has no result yet: it was never started, or it was abandoned before it committed.',
        );
    }

    /** @return list<TellDiagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics->all();
    }

    /**
     * Runs to completion and returns the result, for callers that do not care
     * about progress.
     */
    public function wait(): TellResult
    {
        foreach ($this->checkpoints() as $_) {
        }

        return $this->result();
    }

    /**
     * Teardown for an abandoned run. Must not throw: an exception raised while a
     * generator is force-closed surfaces at whatever statement happened to drop
     * the last reference.
     */
    private function noteIfAbandoned(): void
    {
        if (! $this->started || $this->outcome->isCommitted()) {
            return;
        }
        try {
            $this->diagnostics->recordAbandonedRun();
        } catch (Throwable) {
            // Teardown is best-effort; losing the note must not break the caller.
        }
    }
}
