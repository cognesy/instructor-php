<?php

use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

it('round trips every portable reasoning selection', function (ReasoningSelection $selection) {
    expect(ReasoningSelection::fromArray($selection->toArray()))->toEqual($selection);
})->with([
    'default' => ReasoningSelection::providerDefault(),
    'disabled' => ReasoningSelection::disabled(),
    'enabled' => ReasoningSelection::enabled(),
    'effort' => ReasoningSelection::effort(ReasoningEffort::XHigh),
    'budget' => ReasoningSelection::budget(1024),
    'adaptive' => ReasoningSelection::adaptive(ReasoningEffort::High),
]);

it('rejects a non-positive reasoning budget', function () {
    ReasoningSelection::budget(0);
})->throws(InvalidArgumentException::class);
