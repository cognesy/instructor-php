<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('streams partial responses and assembles final content (Anthropic SSE)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->withStream(true)
        ->replySSEFromJson([
            [ 'delta' => [ 'text' => 'Hel' ] ],
            [ 'delta' => [ 'text' => 'lo' ] ],
            'event: message_stop',
        ], addDone: false);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('anthropic')
        ->withModel('claude-3-haiku-20240307')
        ->withMessages('Greet')
        ->withStreaming(true)
        ->stream();

    iterator_to_array($stream->responses());
    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->content())->toBe('Hello');
});

it('correctly accumulates cumulative token usage in streaming (regression test)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->withStream(true)
        ->replySSEFromJson([
            // Chunk 1: First text with cumulative token count
            [
                'delta' => ['text' => 'Hello'],
                'usage' => ['input_tokens' => 150, 'output_tokens' => 1]
            ],
            // Chunk 2: More text with cumulative token count (not incremental!)
            [
                'delta' => ['text' => ' there'],
                'usage' => ['input_tokens' => 150, 'output_tokens' => 3]  // Total so far, not +2
            ],
            // Chunk 3: Final text with final cumulative totals
            [
                'delta' => ['text' => '!'],
                'usage' => ['input_tokens' => 150, 'output_tokens' => 4]  // Final total
            ],
            // Final message stop event
            [
                'delta' => ['stop_reason' => 'end_turn'],
                'usage' => ['input_tokens' => 150, 'output_tokens' => 4]
            ],
            'event: message_stop',
        ], addDone: false);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('anthropic')
        ->withModel('claude-3-haiku-20240307')
        ->withMessages('Test cumulative usage')
        ->withStreaming(true)
        ->stream();

    // Collect all partial responses to verify accumulation behavior
    $partials = iterator_to_array($stream->responses());
    $final = $stream->final();

    // Verify final content is assembled correctly
    expect($final->content())->toBe('Hello there!');

    // CRITICAL REGRESSION TEST: Token counts should NOT be additive
    // Before fix: input tokens would grow exponentially: 150 -> 300 -> 450 -> 600
    // After fix: input tokens should stay at the maximum cumulative value: 150
    $finalUsage = $final->usage();
    expect($finalUsage)->not->toBeNull();

    // These should be the final cumulative totals, NOT the sum of all chunks
    expect($finalUsage->inputTokens)->toBe(150)
        ->and($finalUsage->outputTokens)->toBe(4);

    // Verify that we're not getting exponential growth that would trigger overflow protection
    expect($finalUsage->inputTokens)->toBeLessThan(1000)  // Well below the 1M safety limit
        ->and($finalUsage->outputTokens)->toBeLessThan(100);

    // Verify intermediate accumulation doesn't cause exponential growth
    expect(count($partials))->toBeGreaterThan(1);  // We should have multiple chunks

    // Each partial should have reasonable token counts (not exponentially growing)
    foreach ($partials as $partial) {
        if ($partial->usage() !== null) {
            expect($partial->usage()->inputTokens)->toBeLessThanOrEqual(150)
                ->and($partial->usage()->outputTokens)->toBeLessThanOrEqual(4);
        }
    }
});

