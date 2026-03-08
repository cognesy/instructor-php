<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('streams partial responses and assembles final content (OpenAI SSE)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->replySSEFromJson([
            ['choices' => [['delta' => ['content' => 'Hel']]]],
            ['choices' => [['delta' => ['content' => 'lo']]]],
            ['choices' => [['delta' => ['content' => '!']]]],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'), httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages('Greet me')
        ->withStreaming(true)
        ->stream();

    // Collect all visible deltas to drive the stream consumption
    $deltas = iterator_to_array($stream->deltas());
    expect($deltas)->not->toBeEmpty();

    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->content())->toBe('Hello!');
});
