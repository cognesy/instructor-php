<?php declare(strict_types=1);

/**
 * Regression tests for tool argument accumulation in InferenceStreamState.
 *
 * These tests verify that the streaming tool-call pipeline correctly
 * accumulates incremental argument deltas and produces valid JSON
 * for downstream ToolCall parsing.
 */

use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

// ── Normal incremental accumulation ──────────────────────────────────────

it('concatenates incremental deltas into valid tool args', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'my_tool', '{"na'), ));
    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', arguments: 'me":"'), ));
    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', arguments: 'John"}'), ));

    $tool = $state->finalResponse()->message()->toolCalls()->first();
    expect($tool->value('name'))->toBe('John');
    expect($state->toolArgsSnapshot())->toBe('{"name":"John"}');
});

// ── Empty args delta is a no-op ──────────────────────────────────────────

it('ignores empty args deltas without corrupting state', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'my_tool', '{"x":1}'), ));

    // Empty args delta (e.g. from a done event that was converted to empty string)
    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'my_tool', ''), ));

    expect($state->toolArgsSnapshot())->toBe('{"x":1}');
    $tool = $state->finalResponse()->message()->toolCalls()->first();
    expect($tool->value('x'))->toBe(1);
});

// ── Multiple independent tool calls ──────────────────────────────────────

it('accumulates args independently per tool call by ID', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'tool_a', '{"x":1}'), ));

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_2', 'call_2', 'tool_b', '{"y":2}'), ));

    $calls = $state->finalResponse()->message()->toolCalls()->all();
    expect($calls)->toHaveCount(2);
    expect($calls[0]->value('x'))->toBe(1);
    expect($calls[1]->value('y'))->toBe(2);
});

// ── Name-only tool calls ─────────────────────────────────────────────────

it('routes argument fragments by stable provider block identity', function () {
    $state = new InferenceStreamState();
    $block = 'provider:tool:0';

    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta(
            $block,
            name: 'search',
            arguments: '{"q":"hel',
        ),
    ));

    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta($block, arguments: 'lo"}'),
    ));

    $tool = $state->finalResponse()->message()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('hello');
});

// ── Partial JSON from incomplete stream ──────────────────────────────────

it('retains partial args when stream ends without completion', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'my_tool', '{"partial":true'), ));

    // Stream ends — no more deltas, no done event
    $rawArgs = $state->toolArgsSnapshot();
    expect($rawArgs)->toBe('{"partial":true');
    // Downstream must resilient-parse this incomplete JSON
});
