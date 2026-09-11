<?php declare(strict_types=1);

use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

it('accumulates json content, reasoning, finish reason, usage, and parsed value in place', function () {
    $state = StructuredOutputStreamState::empty();

    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
            ->withReasoningDelta('test:reasoning:0', 'thinking')
            ->withTextDelta('test:text:0', '{"name"'),
        usage: new InferenceUsage(outputTokens: 1),
    ));
    $state->setValue(['name' => '']);

    $first = $state->partialResponse();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ':"Ann"}'), finishReason: 'stop',
    usage: new InferenceUsage(outputTokens: 2),));
    $state->setValue(['name' => 'Ann']);

    $second = $state->partialResponse();

    expect($first->content())->toBe('{"name"')
        ->and($first->reasoningContent())->toBe('thinking')
        ->and($first->usage()->output())->toBe(1)
        ->and($first->value())->toBe(['name' => ''])
        ->and($second->content())->toBe('{"name":"Ann"}')
        ->and($second->finishReason()->value)->toBe('stop')
        ->and($second->usage()->output())->toBe(3)
        ->and($second->value())->toBe(['name' => 'Ann']);
});

it('accumulates markdown-json content without changing ownership away from state', function () {
    $state = StructuredOutputStreamState::empty();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", "```json\n{\"name\"")));
    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ":\"Ann\"}\n```")));
    $state->setValue(['name' => 'Ann']);

    $partial = $state->partialResponse();

    expect($partial->content())->toBe("```json\n{\"name\":\"Ann\"}\n```")
        ->and($partial->value())->toBe(['name' => 'Ann']);
});

it('accumulates tool argument fragments and exposes the latest tool snapshot', function () {
    $state = StructuredOutputStreamState::empty();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'tool-1', 'tool-1', 'extract', '{"name"'), ));

    $first = $state->partialResponse();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'tool-1', 'tool-1', 'extract', ':"Ann"}'), finishReason: 'stop',));
    $state->setValue(['name' => 'Ann']);

    $second = $state->partialResponse();

    expect($first->toolArgsSnapshot())->toBe('{"name"')
        ->and($first->toolCalls()->first()?->name())->toBe('extract')
        ->and($second->toolArgsSnapshot())->toBe('{"name":"Ann"}')
        ->and($second->toolCalls()->first()?->args())->toBe(['name' => 'Ann'])
        ->and($second->finishReason()->value)->toBe('stop')
        ->and($second->value())->toBe(['name' => 'Ann']);
});

it('memoizes derived tool calls and snapshot until state changes', function () {
    $state = StructuredOutputStreamState::empty();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'tool-1', 'tool-1', 'extract', '{"name":"Ann"}'), ));
    $state->setValue(['name' => 'Ann']);

    $firstToolCalls = $state->toolCalls();
    $secondToolCalls = $state->toolCalls();
    $firstSnapshot = $state->snapshot();
    $secondSnapshot = $state->snapshot();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'tool-1', 'tool-1', arguments: ',"age":30}'), ));

    $updatedToolCalls = $state->toolCalls();
    $updatedSnapshot = $state->snapshot();

    expect($firstToolCalls)->toBe($secondToolCalls)
        ->and($firstSnapshot)->toBe($secondSnapshot)
        ->and($updatedToolCalls)->not->toBe($firstToolCalls)
        ->and($updatedSnapshot)->not->toBe($firstSnapshot);
});

it('memoizes normalized partial and final responses until state changes', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
            ->withTextDelta('test:text:0', '<think>check facts</think>answer'),
    ));

    $partial = $state->partialInferenceResponse();
    $partialResponse = $state->partialResponse();
    $final = $state->finalInferenceResponse();
    $finalResponse = $state->finalResponse();

    expect($state->partialInferenceResponse())->toBe($partial)
        ->and($state->partialResponse())->toBe($partialResponse)
        ->and($state->finalInferenceResponse())->toBe($final)
        ->and($state->finalResponse())->toBe($finalResponse)
        ->and($partial->message()->reasoningContent())->toBe('check facts')
        ->and($partial->message()->content()->toString())->toBe('answer');

    $state->setPreview(['name' => 'Ann']);

    expect($state->partialInferenceResponse())->not->toBe($partial)
        ->and($state->partialResponse())->not->toBe($partialResponse);
});

it('keeps memoized responses when the assigned value is unchanged', function () {
    $state = StructuredOutputStreamState::empty();
    $value = (object) ['name' => 'Ann'];
    $state->setValue($value);
    $response = $state->partialResponse();

    $state->setValue($value);

    expect($state->partialResponse())->toBe($response);
});
