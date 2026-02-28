<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('streams partial responses and assembles final content (Gemini SSE)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->withStream(true)
        ->replySSEFromJson([
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'Hel']]], 'finishReason' => '' ]]],
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'lo']]], 'finishReason' => '' ]]],
            ['candidates' => [[ 'content' => ['parts' => [['text' => '!']]], 'finishReason' => 'STOP' ]]],
        ], addDone: true);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'gemini', httpClient: $http))
        ->withModel('gemini-1.5-flash')
        ->withMessages('Greet')
        ->withStreaming(true)
        ->stream();

    iterator_to_array($stream->responses());
    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->content())->toBe('Hello!');
});

it('correctly accumulates cumulative token usage in streaming (regression test)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->withStream(true)
        ->replySSEFromJson([
            // Chunk 1: First text with cumulative token count
            [
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Hello']]],
                    'finishReason' => ''
                ]],
                'usageMetadata' => ['promptTokenCount' => 125, 'candidatesTokenCount' => 1]
            ],
            // Chunk 2: More text with cumulative token count (not incremental!)
            [
                'candidates' => [[
                    'content' => ['parts' => [['text' => ' world']]],
                    'finishReason' => ''
                ]],
                'usageMetadata' => ['promptTokenCount' => 125, 'candidatesTokenCount' => 3]  // Total so far
            ],
            // Chunk 3: Final text with final cumulative totals
            [
                'candidates' => [[
                    'content' => ['parts' => [['text' => '!']]],
                    'finishReason' => 'STOP'
                ]],
                'usageMetadata' => ['promptTokenCount' => 125, 'candidatesTokenCount' => 4]  // Final total
            ],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'gemini', httpClient: $http))
        ->withModel('gemini-1.5-flash')
        ->withMessages('Test cumulative usage')
        ->withStreaming(true)
        ->stream();

    // Collect all partial responses to verify accumulation behavior
    $partials = iterator_to_array($stream->responses());
    $final = $stream->final();

    // Verify final content is assembled correctly
    expect($final->content())->toBe('Hello world!');

    // CRITICAL REGRESSION TEST: Token counts should NOT be additive
    // Before fix: input tokens would grow exponentially: 125 -> 250 -> 375
    // After fix: input tokens should stay at the maximum cumulative value: 125
    $finalUsage = $final->usage();
    expect($finalUsage)->not->toBeNull();

    // These should be the final cumulative totals, NOT the sum of all chunks
    expect($finalUsage->inputTokens)->toBe(125)
        ->and($finalUsage->outputTokens)->toBe(4);

    // Verify that we're not getting exponential growth that would trigger overflow protection
    expect($finalUsage->inputTokens)->toBeLessThan(1000)  // Well below the 1M safety limit
        ->and($finalUsage->outputTokens)->toBeLessThan(100);

    // Verify intermediate accumulation doesn't cause exponential growth
    expect(count($partials))->toBeGreaterThan(1);  // We should have multiple chunks

    // Each partial should have reasonable token counts (not exponentially growing)
    foreach ($partials as $partial) {
        if ($partial->usage() !== null) {
            expect($partial->usage()->inputTokens)->toBeLessThanOrEqual(125)
                ->and($partial->usage()->outputTokens)->toBeLessThanOrEqual(4);
        }
    }
});

