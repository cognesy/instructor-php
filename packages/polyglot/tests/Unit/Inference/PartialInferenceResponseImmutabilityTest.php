<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('withValue returns new instance and does not mutate original', function () {
    $partial = new PartialInferenceResponse(contentDelta: 'x');

    $withValue = $partial->withValue(['ok' => true]);

    expect($withValue)->not->toBe($partial)
        ->and($partial->hasValue())->toBeFalse()
        ->and($withValue->hasValue())->toBeTrue()
        ->and($withValue->value())->toBe(['ok' => true]);
});

it('InferenceStreamState accumulates without mutating source delta objects', function () {
    $delta1 = new PartialInferenceResponse(contentDelta: 'prev', usage: new Usage(outputTokens: 1));
    $delta2 = new PartialInferenceResponse(contentDelta: 'next', usage: new Usage(outputTokens: 1));

    $state = new InferenceStreamState();
    $state->applyDelta(new PartialInferenceDelta(
        contentDelta: 'prev', usage: new Usage(outputTokens: 1),
    ));
    $state->applyDelta(new PartialInferenceDelta(
        contentDelta: 'next', usage: new Usage(outputTokens: 1),
    ));
    $result = $state->finalResponse();

    // Source deltas remain unmutated
    expect($delta1->content())->toBe('')
        ->and($delta2->content())->toBe('')
        ->and($result->content())->toBe('prevnext')
        ->and($result->usage()->output())->toBe(2);
});
