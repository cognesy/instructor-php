<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('keeps delta payload immutable while carrying explicit value', function () {
    $delta = new PartialInferenceDelta(contentDelta: 'x', value: ['ok' => true]);

    expect($delta->contentDelta)->toBe('x')
        ->and($delta->value)->toBe(['ok' => true]);
});

it('InferenceStreamState accumulates without mutating source delta objects', function () {
    $delta1 = new PartialInferenceDelta(contentDelta: 'prev', usage: new InferenceUsage(outputTokens: 1));
    $delta2 = new PartialInferenceDelta(contentDelta: 'next', usage: new InferenceUsage(outputTokens: 1));

    $state = new InferenceStreamState();
    $state->applyDelta($delta1);
    $state->applyDelta($delta2);
    $result = $state->finalResponse();

    // Source deltas remain unmutated
    expect($delta1->contentDelta)->toBe('prev')
        ->and($delta2->contentDelta)->toBe('next')
        ->and($result->content())->toBe('prevnext')
        ->and($result->usage()->output())->toBe(2);
});
