<?php declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Stream\IterableStream;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Enums\OutputMode;

if (!class_exists('MockStreamSeqPerson')) {
    eval('class MockStreamSeqPerson { public string $name; public int $age; }');
}

it('streams sequence updates incrementally without waiting for full stream completion', function () {
    $gateReached = false;

    $streamChunks = (function () use (&$gateReached): Generator {
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

        yield "data: [DONE]\n\n";

        // Gate: if we reach here before the caller has consumed ANY sequence items,
        // the stream was fully buffered rather than lazily consumed.
        $gateReached = true;
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
        ->withRuntime(makeStructuredRuntime(httpClient: $http, llmDriver: 'openai', outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract people',
            responseModel: Sequence::of('MockStreamSeqPerson'),
            model: 'gpt-4o-mini',
        )
        ->withStreaming(true)
        ->stream();

    $items = [];
    foreach ($stream->sequence() as $item) {
        $items[] = $item;
    }

    expect($items)->toHaveCount(2);
    expect($items[0]->name)->toBe('Ann');
    expect($items[1]->name)->toBe('Bob');
});
