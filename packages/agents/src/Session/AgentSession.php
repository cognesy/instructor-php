<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\Data\AgentDefinition;
use DateTimeImmutable;

final readonly class AgentSession
{
    public function __construct(
        private AgentSessionInfo $header,
        private AgentDefinition $definition,
        private AgentState $state,
    ) {}

    // ACCESSORS ///////////////////////////////////////////////////

    public function info(): AgentSessionInfo {
        return $this->header;
    }

    public function definition(): AgentDefinition {
        return $this->definition;
    }

    public function state(): AgentState {
        return $this->state;
    }

    public function sessionId(): string {
        return $this->header->sessionId();
    }

    public function status(): SessionStatus {
        return $this->header->status();
    }

    public function version(): int {
        return $this->header->version();
    }

    // MUTATORS ////////////////////////////////////////////////////

    // State update — no session status derivation. Session lifecycle is cross-run,
    // execution lifecycle is per-run and resettable (AgentState::forNextExecution()).
    // The orchestrator decides when a session is completed/failed, not the state.
    public function withState(AgentState $state): self {
        return new self(
            header: $this->header,
            definition: $this->definition,
            state: $state,
        );
    }

    // Explicit session lifecycle transitions (all cross-run).
    // Terminal states (completed/failed) are set by SessionRuntime (Phase 2) based on policy.

    public function suspended(): self {
        return $this->withStatus(SessionStatus::Suspended);
    }

    public function resumed(): self {
        return $this->withStatus(SessionStatus::Active);
    }

    public function completed(): self {
        return $this->withStatus(SessionStatus::Completed);
    }

    public function failed(): self {
        return $this->withStatus(SessionStatus::Failed);
    }

    public function withParentId(SessionId|string|null $parentId): self {
        return new self(
            header: $this->header->withParentId($parentId),
            definition: $this->definition,
            state: $this->state,
        );
    }

    // PRIVATE /////////////////////////////////////////////////////

    private function withStatus(SessionStatus $status): self {
        return new self(
            header: $this->header->with(status: $status),
            definition: $this->definition,
            state: $this->state,
        );
    }

    // SERIALIZATION ///////////////////////////////////////////////

    public function toArray(): array {
        return [
            'header' => $this->header->toArray(),
            'definition' => $this->definition->toArray(),
            'state' => $this->state->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            header: AgentSessionInfo::fromArray($data['header']),
            definition: AgentDefinition::fromArray($data['definition']),
            state: AgentState::fromArray($data['state']),
        );
    }

    public static function reconstitute(self $session, int $version, DateTimeImmutable $updatedAt): self {
        return new self(
            header: $session->header->with(version: $version, updatedAt: $updatedAt),
            definition: $session->definition,
            state: $session->state,
        );
    }
}
