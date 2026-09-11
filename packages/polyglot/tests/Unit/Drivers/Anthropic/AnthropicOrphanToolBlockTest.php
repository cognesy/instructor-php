<?php declare(strict_types=1);

/** Anthropic maps wire block indices to stable assistant-message block identities. */

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
        $state->applyDelta($delta);
    }

    expect($state->finalResponse()->message()->content()->toString())->toBe('hello')
        ->and($state->finalResponse()->message()->toolCalls()->count())->toBe(0);
});

it('Anthropic: synthesises a stable id for an orphaned tool-args block', function () {
    // partial_json arriving with no preceding content_block_start for index 3
    $events = [
        json_encode(['type' => 'content_block_delta', 'index' => 3, 'delta' => ['partial_json' => '{"q":"al']]),
        json_encode(['type' => 'content_block_delta', 'index' => 3, 'delta' => ['partial_json' => 'pha"}']]),
    ];

    $deltas = iterator_to_array(anthropicAdapter()->fromStreamDeltas($events));
    $firstToolChunk = $deltas[0]->messageChunks->all()[0];
    $secondToolChunk = $deltas[1]->messageChunks->all()[0];

    expect($firstToolChunk->index)->toBe('3')
        ->and($firstToolChunk->toolCallId)->toBe('idx:3')
        ->and($secondToolChunk->index)->toBe('3')
        ->and($secondToolChunk->toolCallId)->toBe('idx:3');

    $state = new InferenceStreamState();
    foreach ($deltas as $delta) {
        $state->applyDelta($delta);
    }

    $calls = $state->finalResponse()->message()->toolCalls()->all();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]->value('q'))->toBe('alpha');
});

it('Anthropic: still prefers the explicit content_block id over a synthetic one', function () {
    $events = [
        json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_real', 'name' => 'search']]),
        json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['partial_json' => '{"q":"x"}']]),
    ];

    $deltas = iterator_to_array(anthropicAdapter()->fromStreamDeltas($events));
    $firstToolChunk = $deltas[0]->messageChunks->all()[1];
    $secondToolChunk = $deltas[1]->messageChunks->all()[0];

    expect($firstToolChunk->toolCallId)->toBe('toolu_real')
        ->and($secondToolChunk->toolCallId)->toBe('toolu_real');
});
