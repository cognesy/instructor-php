<?php declare(strict_types=1);

/**
 * Regression tests for tool argument accumulation in InferenceStreamState.
 *
 * These tests verify that the streaming tool-call pipeline correctly
 * accumulates incremental argument deltas and produces valid JSON
 * for downstream ToolCall parsing.
 */

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

// ── Normal incremental accumulation ──────────────────────────────────────

it('concatenates incremental deltas into valid tool args', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1',
        toolName: 'my_tool',
        toolArgs: '{"na',
    ));
    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1',
        toolArgs: 'me":"',
    ));
    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1',
        toolArgs: 'John"}',
    ));

    $tool = $state->finalResponse()->toolCalls()->first();
    expect($tool->value('name'))->toBe('John');
    expect($state->toolArgsSnapshot())->toBe('{"name":"John"}');
});

// ── Empty args delta is a no-op ──────────────────────────────────────────

it('ignores empty args deltas without corrupting state', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1',
        toolName: 'my_tool',
        toolArgs: '{"x":1}',
    ));

    // Empty args delta (e.g. from a done event that was converted to empty string)
    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1',
        toolName: 'my_tool',
        toolArgs: '',
    ));

    expect($state->toolArgsSnapshot())->toBe('{"x":1}');
    $tool = $state->finalResponse()->toolCalls()->first();
    expect($tool->value('x'))->toBe(1);
});

// ── Multiple independent tool calls ──────────────────────────────────────

it('accumulates args independently per tool call by ID', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1',
        toolName: 'tool_a',
        toolArgs: '{"x":1}',
    ));

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_2',
        toolName: 'tool_b',
        toolArgs: '{"y":2}',
    ));

    $calls = $state->finalResponse()->toolCalls()->all();
    expect($calls)->toHaveCount(2);
    expect($calls[0]->value('x'))->toBe(1);
    expect($calls[1]->value('y'))->toBe(2);
});

// ── Name-only tool calls ─────────────────────────────────────────────────

it('routes args-only deltas to the last tracked tool', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        toolName: 'search',
        toolArgs: '{"q":"hel',
    ));

    // Args-only delta (no name, no id) — should append to "search"
    $state->applyDelta(new PartialInferenceDelta(
        toolArgs: 'lo"}',
    ));

    $tool = $state->finalResponse()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('hello');
});

// ── Partial JSON from incomplete stream ──────────────────────────────────

it('retains partial args when stream ends without completion', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1',
        toolName: 'my_tool',
        toolArgs: '{"partial":true',
    ));

    // Stream ends — no more deltas, no done event
    $rawArgs = $state->toolArgsSnapshot();
    expect($rawArgs)->toBe('{"partial":true');
    // Downstream must resilient-parse this incomplete JSON
});
