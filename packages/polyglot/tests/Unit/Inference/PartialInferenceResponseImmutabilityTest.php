<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

it('with mutators return new instances and do not mutate original', function () {
    $partial = new PartialInferenceResponse(contentDelta: 'x');

    $withContent = $partial->withContent('content');
    $withReasoning = $partial->withReasoningContent('reasoning');
    $withFinishReason = $partial->withFinishReason('stop');
    $withValue = $partial->withValue(['ok' => true]);
    $withUsageCumulative = $partial->withUsageCumulative(true);

    expect($withContent)->not->toBe($partial)
        ->and($withReasoning)->not->toBe($partial)
        ->and($withFinishReason)->not->toBe($partial)
        ->and($withValue)->not->toBe($partial)
        ->and($withUsageCumulative)->not->toBe($partial);

    expect($partial->content())->toBe('')
        ->and($partial->reasoningContent())->toBe('')
        ->and($partial->finishReason())->toBe('')
        ->and($partial->hasValue())->toBeFalse()
        ->and($partial->isUsageCumulative())->toBeFalse();
});

it('withAccumulatedContent returns new instance and does not mutate source partial', function () {
    $previous = (new PartialInferenceResponse(contentDelta: 'prev', usage: new Usage(outputTokens: 1)))
        ->withAccumulatedContent(PartialInferenceResponse::empty());

    $delta = new PartialInferenceResponse(contentDelta: 'next', usage: new Usage(outputTokens: 1));
    $result = $delta->withAccumulatedContent($previous);

    expect($result)->not->toBe($delta)
        ->and($delta->content())->toBe('')
        ->and($delta->reasoningContent())->toBe('')
        ->and($result->content())->toBe('prevnext')
        ->and($result->usage()->output())->toBe(2);
});
