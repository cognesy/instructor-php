<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;
use Cognesy\Polyglot\Inference\Streaming\VisibilityTracker;

it('tracks visible changes for content, reasoning, tools, finish reason, and value revisions', function () {
    $state = new InferenceStreamState();
    $tracker = new VisibilityTracker();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'Hel')));
    expect($tracker->hasVisibleChange($state))->toBeTrue();
    $tracker->remember($state);

    $state->applyDelta(new PartialInferenceDelta());
    expect($tracker->hasVisibleChange($state))->toBeFalse();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
        ->withReasoningDelta('test:reasoning:0', 'thinking')));
    expect($tracker->hasVisibleChange($state))->toBeTrue();
    $tracker->remember($state);

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'search', '{"q":"Ann"}')));
    expect($tracker->hasVisibleChange($state))->toBeTrue();
    $tracker->remember($state);

    $state->applyDelta(new PartialInferenceDelta(finishReason: 'stop'));
    expect($tracker->hasVisibleChange($state))->toBeTrue();
    $tracker->remember($state);

    $state->applyDelta(new PartialInferenceDelta(value: ['name' => 'Ann']));
    expect($tracker->hasVisibleChange($state))->toBeTrue();
});
