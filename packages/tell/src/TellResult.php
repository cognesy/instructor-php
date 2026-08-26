<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Tell\Diagnostics\TellDiagnostic;

final readonly class TellResult
{
    /**
     * @param list<string> $warnings
     * @param list<TellDiagnostic> $diagnostics
     * @param 'current'|'invocation'|null $branchSource
     */
    public function __construct(
        private AgentState $state,
        private array $warnings = [],
        private bool $transient = false,
        private bool $durable = false,
        private ?string $session = null,
        private ?string $workspace = null,
        private ?string $branch = null,
        private ?string $branchSource = null,
        private array $diagnostics = [],
    ) {}

    public function state(): AgentState
    {
        return $this->state;
    }

    public function status(): ?ExecutionStatus
    {
        return $this->state->status();
    }

    public function text(): string
    {
        return $this->state->finalResponse()->toString();
    }

    public function usage(): InferenceUsage
    {
        return $this->state->usage();
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return list<TellDiagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function isCompleted(): bool
    {
        return $this->state->status() === ExecutionStatus::Completed;
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }

    public function isDurable(): bool
    {
        return $this->durable;
    }

    public function session(): ?string
    {
        return $this->session;
    }

    public function workspace(): ?string
    {
        return $this->workspace;
    }

    public function branch(): ?string
    {
        return $this->branch;
    }

    /** @return 'current'|'invocation'|null */
    public function branchSource(): ?string
    {
        return $this->branchSource;
    }
}
