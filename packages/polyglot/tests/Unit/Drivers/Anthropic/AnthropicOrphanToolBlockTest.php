<?php declare(strict_types=1);

/**
 * Anthropic used to return an empty tool id when a tool block's arguments arrived
 * without a preceding content_block_start (so no id was ever remembered for that
 * block index), leaving InferenceStreamState to guess. It now synthesises a stable
 * 'idx:N' id from the wire index, as OpenAI and Gemini already did.
 *
 * The synthetic id must be minted ONLY for tool-bearing events: a non-empty toolId
 * alone makes InferenceStreamState treat a delta as a tool delta, so minting one for
 * every event would start a phantom tool call on each text chunk.
 */

use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

function anthropicAdapter(): AnthropicResponseAdapter {
    return new AnthropicResponseAdapter(new AnthropicUsageFormat());
}

it('Anthropic: does not mint a tool id for plain text deltas', function () {
    $events = [
        json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]),
        json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['text_delta' => 'hello', 'text' => 'hello']]),
    ];

    $state = new InferenceStreamState();
    foreach (anthropicAdapter()->fromStreamDeltas($events) as $delta) {
        expect($delta->toolId)->toBe('');
        $state->applyDelta($delta);
    }

    expect($state->finalResponse()->toolCalls()->count())->toBe(0);
});

it('Anthropic: synthesises a stable id for an orphaned tool-args block', function () {
    // partial_json arriving with no preceding content_block_start for index 3
    $events = [
        json_encode(['type' => 'content_block_delta', 'index' => 3, 'delta' => ['partial_json' => '{"q":"al']]),
        json_encode(['type' => 'content_block_delta', 'index' => 3, 'delta' => ['partial_json' => 'pha"}']]),
    ];

    $deltas = iterator_to_array(anthropicAdapter()->fromStreamDeltas($events));

    expect($deltas[0]->toolId)->toBe('idx:3')
        ->and($deltas[1]->toolId)->toBe('idx:3'); // remembered, so both fragments agree

    $state = new InferenceStreamState();
    foreach ($deltas as $delta) {
        $state->applyDelta($delta);
    }

    $calls = $state->finalResponse()->toolCalls()->all();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]->value('q'))->toBe('alpha');
});

it('Anthropic: still prefers the explicit content_block id over a synthetic one', function () {
    $events = [
        json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_real', 'name' => 'search']]),
        json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['partial_json' => '{"q":"x"}']]),
    ];

    $deltas = iterator_to_array(anthropicAdapter()->fromStreamDeltas($events));

    expect($deltas[0]->toolId)->toBe('toolu_real')
        ->and($deltas[1]->toolId)->toBe('toolu_real');
});
