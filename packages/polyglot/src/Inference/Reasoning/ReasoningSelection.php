<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use InvalidArgumentException;

/** Portable caller intent for provider reasoning controls. */
final readonly class ReasoningSelection
{
    private function __construct(
        public ReasoningSelectionKind $kind,
        public ?ReasoningEffort $effort = null,
        public ?int $budgetTokens = null,
    ) {
        $this->assertValid();
    }

    public static function providerDefault(): self
    {
        return new self(ReasoningSelectionKind::Default);
    }

    public static function disabled(): self
    {
        return new self(ReasoningSelectionKind::Disabled);
    }

    public static function enabled(): self
    {
        return new self(ReasoningSelectionKind::Enabled);
    }

    public static function withEffort(ReasoningEffort $effort): self
    {
        return new self(ReasoningSelectionKind::Effort, effort: $effort);
    }

    public static function withBudget(int $budgetTokens): self
    {
        return new self(ReasoningSelectionKind::Budget, budgetTokens: $budgetTokens);
    }

    public static function adaptive(?ReasoningEffort $effort = null): self
    {
        return new self(ReasoningSelectionKind::Adaptive, effort: $effort);
    }

    public function isDefault(): bool
    {
        return $this->kind === ReasoningSelectionKind::Default;
    }

    public function toArray(): array
    {
        $data = ['kind' => $this->kind->value];
        $data = $this->effort === null ? $data : [...$data, 'effort' => $this->effort->value];

        return $this->budgetTokens === null
            ? $data
            : [...$data, 'budgetTokens' => $this->budgetTokens];
    }

    public static function fromArray(array $data): self
    {
        $kind = ReasoningSelectionKind::from((string) ($data['kind'] ?? 'default'));
        $effort = isset($data['effort'])
            ? ReasoningEffort::from((string) $data['effort'])
            : null;
        $budget = isset($data['budgetTokens']) ? (int) $data['budgetTokens'] : null;

        return new self($kind, $effort, $budget);
    }

    private function assertValid(): void
    {
        $valid = match ($this->kind) {
            ReasoningSelectionKind::Default,
            ReasoningSelectionKind::Disabled,
            ReasoningSelectionKind::Enabled => $this->effort === null && $this->budgetTokens === null,
            ReasoningSelectionKind::Effort => $this->effort !== null && $this->budgetTokens === null,
            ReasoningSelectionKind::Budget => $this->effort === null && ($this->budgetTokens ?? 0) > 0,
            ReasoningSelectionKind::Adaptive => $this->budgetTokens === null,
        };

        if ($valid) {
            return;
        }

        throw new InvalidArgumentException("Invalid {$this->kind->value} reasoning selection.");
    }
}
