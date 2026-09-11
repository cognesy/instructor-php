<?php declare(strict_types=1);

use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunk;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * E1 (research/v2-cleanup-plan/02 Phase E): instructor's stream state now
 * delegates tool accumulation to polyglot's InferenceStreamState. These tests
 * pin the two semantics the delegation FIXED (previously divergent):
 *  A) repeated-name tool deltas continue the active tool (no fragmentation)
 *  B) args arriving before any tool key are buffered, not dropped
 */

it('continues a tool call when chunks use the same ordered block index', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(toolDelta('provider:tool:0', 'extract', '{"a":'));
    $state->applyDelta(toolDelta('provider:tool:0', 'extract', '1}'));

    $calls = $state->toolCalls();

    expect($calls->count())->toBe(1);
    expect($state->toolArgsSnapshot())->toBe('{"a":1}');
});

it('starts a new tool call when the ordered block index changes', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(toolDelta('provider:tool:0', 'search', '{"q":"x"}'));
    $state->applyDelta(toolDelta('provider:tool:1', 'extract', '{"a":1}'));

    expect($state->toolCalls()->count())->toBe(2);
});

it('accumulates arguments before the provider supplies a tool name', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(toolDelta('provider:tool:0', '', '{"x":'));
    $state->applyDelta(toolDelta('provider:tool:0', 'extract', '1}'));

    expect($state->toolCalls()->count())->toBe(1);
    expect($state->toolArgsSnapshot())->toBe('{"x":1}');
});

it('keeps block-indexed accumulation stable when later chunks omit the tool id', function () {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta(toolDelta('provider:tool:0', 'extract', '{"a":', 'call_1'));
    $state->applyDelta(toolDelta('provider:tool:0', '', '1}'));

    $calls = $state->toolCalls();

    expect($calls->count())->toBe(1);
    expect($state->toolArgsSnapshot())->toBe('{"a":1}');
    expect($state->toolKey())->toBe('provider:tool:0');
});

function toolDelta(string $index, string $name, string $arguments, string $id = ''): PartialInferenceDelta
{
    return new PartialInferenceDelta(messageChunks: new AssistantMessageChunks(
        AssistantMessageChunk::toolCallDelta($index, $id, $name, $arguments),
    ));
}
