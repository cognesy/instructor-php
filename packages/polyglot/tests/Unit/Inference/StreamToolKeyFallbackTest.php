<?php declare(strict_types=1);

/**
 * Covers InferenceStreamState::resolveToolKey()'s fallback ladder, and in particular
 * the pendingToolArgs buffer -- the one path that had no test.
 *
 * The ladder exists for providers that stream tool calls without ids. All bundled
 * adapters now synthesise an id from the wire index, so these paths are reached only
 * by third-party drivers implementing CanTranslateInferenceResponse directly.
 */

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('buffers args that arrive before any tool is identified, then attaches them', function () {
    $state = new InferenceStreamState();

    // No id, no name, no tool started yet -> resolveToolKey() returns '' and the
    // fragment goes into pendingToolArgs rather than being dropped.
    $state->applyDelta(new PartialInferenceDelta(toolArgs: '{"q":'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '"alpha"}'));

    $calls = $state->finalResponse()->toolCalls()->all();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]->name())->toBe('search')
        ->and($calls[0]->value('q'))->toBe('alpha');
});

it('drops orphaned args when no tool is ever identified', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(toolArgs: '{"orphan":true}'));

    expect($state->finalResponse()->toolCalls()->count())->toBe(0);
});

it('keeps an id-keyed tool distinct from a later name-keyed one', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(toolId: 'call_1', toolName: 'search', toolArgs: '{"a":1}'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'other', toolArgs: '{"b":2}'));

    $calls = $state->finalResponse()->toolCalls()->all();

    expect($calls)->toHaveCount(2)
        ->and($calls[0]->name())->toBe('search')
        ->and($calls[1]->name())->toBe('other');
});
