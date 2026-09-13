<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

final readonly class TellResult
{
    private TellTermination $termination;

    /**
     * @param  list<string>  $warnings
     * @param  list<TellDiagnostic>  $diagnostics
     */
    public function __construct(
        private AgentState $state,
        private TellExecutionMode $requestedMode,
        private TellExecutionMode $mode,
        private TellPublication $publication,
        private TellTraceReference $trace,
        private array $warnings = [],
        private array $diagnostics = [],
    ) {
        $this->termination = TellTermination::fromState($state);
    }

    public function state(): AgentState {
        return $this->state;
    }

    public function status(): ExecutionStatus {
        return $this->termination->status;
    }

    public function text(): string {
        return $this->state->finalResponse()->toString();
    }

    public function usage(): InferenceUsage {
        return $this->termination->usage;
    }

    /** @return list<string> */
    public function warnings(): array {
        return $this->warnings;
    }

    /** @return list<TellDiagnostic> */
    public function diagnostics(): array {
        return $this->diagnostics;
    }

    public function isCompleted(): bool {
        return $this->termination->isCompleted();
    }

    public function isTransient(): bool {
        return $this->requestedMode === TellExecutionMode::Transient;
    }

    public function isPublished(): bool {
        return $this->publication->isPublished();
    }

    public function requestedMode(): TellExecutionMode {
        return $this->requestedMode;
    }

    public function mode(): TellExecutionMode {
        return $this->mode;
    }

    public function termination(): TellTermination {
        return $this->termination;
    }

    public function publication(): TellPublication {
        return $this->publication;
    }

    public function trace(): TellTraceReference {
        return $this->trace;
    }

    public function session(): ?string {
        return $this->publication->session;
    }

    public function workspace(): ?string {
        return $this->publication->workspace;
    }

    public function branch(): ?string {
        return $this->publication->branch;
    }

    /** @return 'current'|'invocation'|null */
    public function branchSource(): ?string {
        return $this->publication->branchSource;
    }
}
