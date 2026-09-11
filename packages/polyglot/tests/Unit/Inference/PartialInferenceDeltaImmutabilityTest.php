<?php

use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('keeps delta payload immutable while carrying explicit value', function () {
    $delta = new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withTextDelta('test:text:0', 'x'),
        value: ['ok' => true],
    );

    expect($delta->messageChunks->textDelta())->toBe('x')
        ->and($delta->value)->toBe(['ok' => true]);
});

it('InferenceStreamState accumulates without mutating source delta objects', function () {
    $delta1 = new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withTextDelta('test:text:0', 'prev'),
        usage: new InferenceUsage(outputTokens: 1),
    );
    $delta2 = new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withTextDelta('test:text:0', 'next'),
        usage: new InferenceUsage(outputTokens: 1),
    );

    $state = new InferenceStreamState();
    $state->applyDelta($delta1);
    $state->applyDelta($delta2);
    $result = $state->finalResponse();

    // Source deltas remain unmutated
    expect($delta1->messageChunks->textDelta())->toBe('prev')
        ->and($delta2->messageChunks->textDelta())->toBe('next')
        ->and($result->message()->content()->toString())->toBe('prevnext')
        ->and($result->usage()->output())->toBe(2);
});
