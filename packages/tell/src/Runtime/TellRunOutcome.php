<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Closure;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Data\TellResult;

/**
 * Carries a run's outcome out of its generator as soon as the outcome exists,
 * rather than leaving it behind a `return` that only a fully drained consumer
 * can reach. A runner records here at the moment it commits; the surrounding
 * TellRun reads it whether or not the caller kept iterating.
 */
final class TellRunOutcome
{
    private ?AgentState $state = null;
    private ?TellResult $result = null;
    private bool $committed = false;

    /** @var list<string> */
    private array $warnings = [];

    /** @var Closure(AgentState): TellResult|null */
    private ?Closure $builder = null;

    /**
     * Supplies the mode-specific way to turn a terminal state into a result, so
     * a caller that stops iterating early still gets the durability, workspace
     * and branch facts its mode would have reported.
     *
     * @param  callable(AgentState): TellResult  $builder
     */
    public function useBuilder(callable $builder): void {
        $this->builder = Closure::fromCallable($builder);
    }

    /** Records the terminal state at the instant its durable effect is applied. */
    public function recordCommitted(AgentState $state): void {
        $this->state ??= $state;
        $this->committed = true;
    }

    /** Records the assembled result for the same run. */
    public function recordResult(TellResult $result): void {
        $this->result = $result;
        $this->state ??= $result->state();
        $this->committed = true;
    }

    /** @param list<string> $warnings */
    public function recordWarnings(array $warnings): void {
        $this->warnings = $warnings;
    }

    /** @return list<string> */
    public function warnings(): array {
        return $this->warnings;
    }

    public function state(): ?AgentState {
        return $this->state;
    }

    public function result(): ?TellResult {
        if ($this->result !== null) {
            return $this->result;
        }
        if ($this->state === null || $this->builder === null) {
            return null;
        }

        return $this->result = ($this->builder)($this->state);
    }

    public function isCommitted(): bool {
        return $this->committed;
    }
}
