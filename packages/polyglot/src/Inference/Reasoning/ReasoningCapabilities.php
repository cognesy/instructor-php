<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

/** Model- and protocol-specific reasoning capability contract. */
final readonly class ReasoningCapabilities
{
    public function __construct(
        public bool $known,
        public ReasoningSelectionKinds $selectionKinds,
        public ReasoningEffortMappings $effortMappings,
        public ?ReasoningBudgetRange $budgetRange = null,
        public ReasoningDefaultBehavior $defaultBehavior = ReasoningDefaultBehavior::Unknown,
        public bool $reasoningContentVisible = false,
        public bool $reasoningTokensVisible = false,
    ) {}

    public static function unknown(): self
    {
        return new self(
            known: false,
            selectionKinds: ReasoningSelectionKinds::none(),
            effortMappings: ReasoningEffortMappings::none(),
        );
    }

    public function supports(ReasoningSelection $selection): bool
    {
        if ($selection->isDefault()) {
            return true;
        }

        if (! $this->known || ! $this->selectionKinds->contains($selection->kind)) {
            return false;
        }

        return match ($selection->kind) {
            ReasoningSelectionKind::Effort => $selection->effort !== null
                && $this->effortMappings->find($selection->effort)?->quality->isAcceptedByDefault() === true,
            ReasoningSelectionKind::Adaptive => $selection->effort === null
                || $this->effortMappings->find($selection->effort)?->quality->isAcceptedByDefault() === true,
            ReasoningSelectionKind::Budget => $selection->budgetTokens !== null
                && $this->budgetRange?->contains($selection->budgetTokens) === true,
            ReasoningSelectionKind::Disabled => $this->defaultBehavior !== ReasoningDefaultBehavior::Mandatory,
            default => true,
        };
    }

    public function supportsEffort(): bool
    {
        return $this->known
            && $this->selectionKinds->contains(ReasoningSelectionKind::Effort)
            && $this->effortMappings->all() !== [];
    }
}
