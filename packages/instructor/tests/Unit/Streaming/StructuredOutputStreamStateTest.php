<?php declare(strict_types=1);

use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;

it('accumulates json content, reasoning, finish reason, usage, and parsed value in place', function () {
    $state = StructuredOutputStreamState::empty();

    $state->applyDelta(new PartialInferenceDelta(
        contentDelta: '{"name"',
        reasoningContentDelta: 'thinking',
        usage: new Usage(outputTokens: 1),
    ));
    $state->setValue(['name' => '']);

    $first = $state->partialResponse();

    $state->applyDelta(new PartialInferenceDelta(
        contentDelta: ':"Ann"}',
        finishReason: 'stop',
        usage: new Usage(outputTokens: 2),
    ));
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

    $state->applyDelta(new PartialInferenceDelta(contentDelta: "```json\n{\"name\""));
    $state->applyDelta(new PartialInferenceDelta(contentDelta: ":\"Ann\"}\n```"));
    $state->setValue(['name' => 'Ann']);

    $partial = $state->partialResponse();

    expect($partial->content())->toBe("```json\n{\"name\":\"Ann\"}\n```")
        ->and($partial->value())->toBe(['name' => 'Ann']);
});

it('accumulates tool argument fragments and exposes the latest tool snapshot', function () {
    $state = StructuredOutputStreamState::empty();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'tool-1',
        toolName: 'extract',
        toolArgs: '{"name"',
    ));

    $first = $state->partialResponse();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'tool-1',
        toolName: 'extract',
        toolArgs: ':"Ann"}',
        finishReason: 'stop',
    ));
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

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'tool-1',
        toolName: 'extract',
        toolArgs: '{"name":"Ann"}',
    ));
    $state->setValue(['name' => 'Ann']);

    $firstToolCalls = $state->toolCalls();
    $secondToolCalls = $state->toolCalls();
    $firstSnapshot = $state->snapshot();
    $secondSnapshot = $state->snapshot();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'tool-1',
        toolArgs: ',"age":30}',
    ));

    $updatedToolCalls = $state->toolCalls();
    $updatedSnapshot = $state->snapshot();

    expect($firstToolCalls)->toBe($secondToolCalls)
        ->and($firstSnapshot)->toBe($secondSnapshot)
        ->and($updatedToolCalls)->not->toBe($firstToolCalls)
        ->and($updatedSnapshot)->not->toBe($firstSnapshot);
});
