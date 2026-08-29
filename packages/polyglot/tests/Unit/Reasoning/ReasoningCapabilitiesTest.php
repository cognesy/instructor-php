<?php

use Cognesy\Polyglot\Inference\Reasoning\ReasoningBudgetRange;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningCapabilities;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningDefaultBehavior;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffortMapping;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffortMappings;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningMappingQuality;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelectionKind;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelectionKinds;

it('accepts exact and documented mappings but rejects lossy ones', function () {
    $capabilities = new ReasoningCapabilities(
        known: true,
        selectionKinds: new ReasoningSelectionKinds(ReasoningSelectionKind::Effort),
        effortMappings: new ReasoningEffortMappings(
            new ReasoningEffortMapping(
                ReasoningEffort::Low,
                'low',
                ReasoningEffort::Low,
            ),
            new ReasoningEffortMapping(
                ReasoningEffort::Medium,
                'high',
                ReasoningEffort::High,
                ReasoningMappingQuality::Documented,
            ),
            new ReasoningEffortMapping(
                ReasoningEffort::Max,
                'enabled',
                ReasoningEffort::High,
                ReasoningMappingQuality::Lossy,
            ),
        ),
    );

    expect($capabilities->supports(ReasoningSelection::withEffort(ReasoningEffort::Low)))->toBeTrue()
        ->and($capabilities->supports(ReasoningSelection::withEffort(ReasoningEffort::Medium)))->toBeTrue()
        ->and($capabilities->supports(ReasoningSelection::withEffort(ReasoningEffort::Max)))->toBeFalse();
});

it('validates budgets and mandatory reasoning independently', function () {
    $capabilities = new ReasoningCapabilities(
        known: true,
        selectionKinds: new ReasoningSelectionKinds(
            ReasoningSelectionKind::Disabled,
            ReasoningSelectionKind::Budget,
        ),
        effortMappings: ReasoningEffortMappings::none(),
        budgetRange: new ReasoningBudgetRange(64, 4096),
        defaultBehavior: ReasoningDefaultBehavior::Mandatory,
    );

    expect($capabilities->supports(ReasoningSelection::withBudget(64)))->toBeTrue()
        ->and($capabilities->supports(ReasoningSelection::withBudget(4097)))->toBeFalse()
        ->and($capabilities->supports(ReasoningSelection::disabled()))->toBeFalse();
});

it('treats unknown capabilities as typed unsupported', function () {
    expect(ReasoningCapabilities::unknown()->supports(ReasoningSelection::enabled()))->toBeFalse()
        ->and(ReasoningCapabilities::unknown()->supports(ReasoningSelection::providerDefault()))->toBeTrue();
});
