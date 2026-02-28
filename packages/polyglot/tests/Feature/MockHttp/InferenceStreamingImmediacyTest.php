<?php declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Stream\IterableStream;
use Cognesy\Polyglot\Inference\Inference;

it('does not pre-buffer SSE stream before yielding first partial', function () {
    $allowThirdChunk = false;

    $streamChunks = (function () use (&$allowThirdChunk): Generator {
        yield "data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n\n";
        yield "data: {\"choices\":[{\"delta\":{\"content\":\"lo\"}}]}\n\n";
        if (!$allowThirdChunk) {
            throw new RuntimeException('Third chunk consumed before unlock');
        }
        yield "data: {\"choices\":[{\"delta\":{\"content\":\"!\"}}]}\n\n";
        yield "data: [DONE]\n\n";
    })();

    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->reply(fn() => HttpResponse::streaming(
            statusCode: 200,
            headers: ['content-type' => 'text/event-stream'],
            stream: new IterableStream($streamChunks),
        ));

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = Inference::fromRuntime(
        \Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'openai', httpClient: $http),
    )
        ->withModel('gpt-4o-mini')
        ->withMessages('Greet me')
        ->withStreaming(true)
        ->stream();

    $iter = $stream->responses();
    expect($iter->valid())->toBeTrue();
    expect($iter->current()->content())->toBe('Hel');

    // If stream is pre-buffered, the gated source would already throw above.
    $allowThirdChunk = true;

    $iter->next();
    expect($iter->valid())->toBeTrue();
    expect($iter->current()->content())->toBe('Hello');

    $iter->next();
    expect($iter->valid())->toBeTrue();
    expect($iter->current()->content())->toBe('Hello!');

    $iter->next();
    expect($iter->valid())->toBeFalse();

    $final = $stream->final();
    expect($final)->not()->toBeNull();
    expect($final->content())->toBe('Hello!');
});
