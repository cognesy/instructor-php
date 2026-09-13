<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Execution;

use Closure;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Data\TellTermination;

/**
 * Carries a run's outcome out of its generator as soon as the outcome exists,
 * rather than leaving it behind a `return` that only a fully drained consumer
 * can reach. A runner records here at settlement or publication; the surrounding
 * TellRun reads it whether or not the caller kept iterating. Execution
 * settlement and publication are independent facts.
 */
final class TellRunOutcome
{
    private ?AgentState $state = null;
    private ?TellResult $result = null;
    private TellPublication $publication;

    /** @var Closure(AgentState, TellPublication): TellResult|null */
    private ?Closure $builder = null;

    public function __construct() {
        $this->publication = TellPublication::notApplicable();
    }

    /**
     * Supplies the mode-specific way to turn a terminal state into a result, so
     * a caller that stops iterating early still gets the durability, workspace
     * and branch facts its mode would have reported.
     *
     * @param  callable(AgentState, TellPublication): TellResult  $builder
     */
    public function useBuilder(callable $builder, TellPublication $publication): void {
        $this->builder = Closure::fromCallable($builder);
        $this->publication = $publication;
    }

    /** Records the first terminal state without implying any durable effect. */
    public function recordSettled(AgentState $state): void {
        $this->state ??= $state;
    }

    /** Records a successfully published terminal state. */
    public function recordPublished(AgentState $state, TellPublication $publication): void {
        $this->state ??= $state;
        $this->publication = $publication;
    }

    /** Records a terminal state whose requested publication failed. */
    public function recordPublicationFailed(AgentState $state, TellPublication $publication): void {
        $this->state ??= $state;
        $this->publication = $publication;
    }

    /** Records the assembled result for the same run. */
    public function recordResult(TellResult $result): void {
        $this->result = $result;
        $this->state ??= $result->state();
        $this->publication = $result->publication();
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

        return $this->result = ($this->builder)($this->state, $this->publication);
    }

    public function isSettled(): bool {
        return $this->state !== null;
    }

    public function isPublished(): bool {
        return $this->publication->isPublished();
    }

    public function termination(): ?TellTermination {
        return match ($this->state) {
            null => null,
            default => TellTermination::fromState($this->state),
        };
    }

    public function publication(): TellPublication {
        return $this->publication;
    }
}
