<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use Cognesy\Polyglot\Inference\Models\ModelRecordFields;
use InvalidArgumentException;

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

    public static function unknown(): self {
        return new self(
            known: false,
            selectionKinds: ReasoningSelectionKinds::none(),
            effortMappings: ReasoningEffortMappings::none(),
        );
    }

    public static function fromArray(array $data): self {
        ModelRecordFields::validate($data, [
            'selections', 'efforts', 'budget', 'default', 'contentVisible', 'tokensVisible',
        ], 'reasoning');
        if ($data === []) {
            return self::unknown();
        }

        $selections = $data['selections'] ?? [];
        $efforts = $data['efforts'] ?? [];
        $budget = $data['budget'] ?? null;
        $default = $data['default'] ?? ReasoningDefaultBehavior::Unknown->value;
        $contentVisible = $data['contentVisible'] ?? false;
        $tokensVisible = $data['tokensVisible'] ?? false;
        if (!is_array($selections) || !is_array($efforts)
            || ($budget !== null && !is_array($budget))
            || !is_string($default) || !is_bool($contentVisible) || !is_bool($tokensVisible)
        ) {
            throw new InvalidArgumentException('Invalid reasoning capability record.');
        }

        return new self(
            known: true,
            selectionKinds: ReasoningSelectionKinds::fromArray($selections),
            effortMappings: ReasoningEffortMappings::fromArray($efforts),
            budgetRange: $budget === null ? null : ReasoningBudgetRange::fromArray($budget),
            defaultBehavior: ReasoningDefaultBehavior::tryFrom($default)
                ?? throw new InvalidArgumentException("Invalid reasoning default behavior: {$default}"),
            reasoningContentVisible: $contentVisible,
            reasoningTokensVisible: $tokensVisible,
        );
    }

    public function supports(ReasoningSelection $selection): bool {
        if ($selection->isDefault()) {
            return true;
        }

        if (!$this->known || !$this->selectionKinds->contains($selection->kind)) {
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

    public function supportsEffort(): bool {
        return $this->known
            && $this->selectionKinds->contains(ReasoningSelectionKind::Effort)
            && $this->effortMappings->all() !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        if (!$this->known) {
            return [];
        }

        $efforts = $this->effortMappings->toArray();

        return [
            'selections' => $this->selectionKinds->toArray(),
            ...match ($efforts) {
                [] => [],
                default => ['efforts' => $efforts],
            },
            ...match ($this->budgetRange) {
                null => [],
                default => ['budget' => $this->budgetRange->toArray()],
            },
            ...match ($this->defaultBehavior) {
                ReasoningDefaultBehavior::Unknown => [],
                default => ['default' => $this->defaultBehavior->value],
            },
            ...match ($this->reasoningContentVisible) {
                false => [],
                true => ['contentVisible' => true],
            },
            ...match ($this->reasoningTokensVisible) {
                false => [],
                true => ['tokensVisible' => true],
            },
        ];
    }
}
