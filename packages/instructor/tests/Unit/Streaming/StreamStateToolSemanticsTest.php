<?php declare(strict_types=1);

use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * E1 (research/v2-cleanup-plan/02 Phase E): instructor's stream state now
 * delegates tool accumulation to polyglot's InferenceStreamState. These tests
 * pin the two semantics the delegation FIXED (previously divergent):
 *  A) repeated-name tool deltas continue the active tool (no fragmentation)
 *  B) args arriving before any tool key are buffered, not dropped
 */

it('continues the active tool when providers repeat the tool name per delta', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(new PartialInferenceDelta(toolName: 'extract', toolArgs: '{"a":'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'extract', toolArgs: '1}'));

    $calls = $state->toolCalls();

    expect($calls->count())->toBe(1);
    expect($state->toolArgsSnapshot())->toBe('{"a":1}');
});

it('starts a new tool when a different name arrives', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"x"}'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'extract', toolArgs: '{"a":1}'));

    expect($state->toolCalls()->count())->toBe(2);
});

it('buffers tool args that arrive before any tool key and prepends them', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(new PartialInferenceDelta(toolArgs: '{"x":'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'extract', toolArgs: '1}'));

    expect($state->toolCalls()->count())->toBe(1);
    expect($state->toolArgsSnapshot())->toBe('{"x":1}');
});

it('keeps id-keyed accumulation stable across id-less continuation deltas', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(new PartialInferenceDelta(toolId: 'call_1', toolName: 'extract', toolArgs: '{"a":'));
    $state->applyDelta(new PartialInferenceDelta(toolArgs: '1}'));

    $calls = $state->toolCalls();

    expect($calls->count())->toBe(1);
    expect($state->toolArgsSnapshot())->toBe('{"a":1}');
    expect($state->toolKey())->toBe('id:call_1');
});
