<?php declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Stream\IterableStream;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

if (!class_exists('MockStreamSeqPerson')) {
    eval('class MockStreamSeqPerson { public string $name; public int $age; }');
}

it('streams sequence updates incrementally without waiting for full stream completion', function () {
    $allowThirdChunk = false;

    $streamChunks = (function () use (&$allowThirdChunk): Generator {
        yield 'data: ' . json_encode([
            'choices' => [[
                'delta' => ['content' => '{"list":[{"name":"Ann","age":30}'],
            ]],
        ], JSON_UNESCAPED_SLASHES) . "\n\n";

        yield 'data: ' . json_encode([
            'choices' => [[
                'delta' => ['content' => ',{"name":"Bob"'],
            ]],
        ], JSON_UNESCAPED_SLASHES) . "\n\n";

        yield 'data: ' . json_encode([
            'choices' => [[
                'delta' => ['content' => ',"age":40}]}'],
            ]],
        ], JSON_UNESCAPED_SLASHES) . "\n\n";

        if (!$allowThirdChunk) {
            throw new RuntimeException('Terminal chunk consumed before unlock');
        }
        yield "data: [DONE]\n\n";
    })();

    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->withJsonSubset(['model' => 'gpt-4o-mini', 'stream' => true])
        ->reply(fn() => HttpResponse::streaming(
            statusCode: 200,
            headers: ['content-type' => 'text/event-stream'],
            stream: new IterableStream($streamChunks),
        ));

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(httpClient: $http, llmDriver: 'openai'))
        ->withOutputMode(OutputMode::Json)
        ->with(
            messages: 'Extract people',
            responseModel: Sequence::of('MockStreamSeqPerson'),
            model: 'gpt-4o-mini',
        )
        ->withStreaming(true)
        ->stream();

    $iter = $stream->sequence();

    expect($iter->valid())->toBeTrue();
    $first = $iter->current();
    expect($first->count())->toBe(1);
    expect($first->get(0)->name)->toBe('Ann');

    // If sequence stream was pre-buffered, gated chunk access would already fail above.
    $allowThirdChunk = true;

    $iter->next();
    expect($iter->valid())->toBeTrue();
    $second = $iter->current();
    expect($second->count())->toBe(2);
    expect($second->get(0)->name)->toBe('Ann');
    expect($second->get(1)->name)->toBe('Bob');
});
