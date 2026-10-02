<?php

declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

it('returns decoded vectors through the Perplexity embeddings driver', function (): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('https://api.perplexity.ai/v1/embeddings')
        ->header('Authorization', 'Bearer test')
        ->withJsonSubset(['model' => 'pplx-embed-v1-0.6b', 'encoding_format' => 'base64_int8'])
        ->times(1)
        ->replyJson([
            'object' => 'list',
            'data' => [['object' => 'embedding', 'index' => 0, 'embedding' => base64_encode(pack('c*', 1, -2, 127, -128))]],
            'model' => 'pplx-embed-v1-0.6b',
            'usage' => ['prompt_tokens' => 2, 'total_tokens' => 2],
        ]);

    $vectors = perplexityEmbeddings($mock)->withInputs(['hello'])->vectors();

    expect($vectors)->toHaveCount(1)
        ->and($vectors[0]->values())->toBe([1.0, -2.0, 127.0, -128.0]);
});

it('routes contextualized models to the contextualized endpoint', function (): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('https://api.perplexity.ai/v1/contextualizedembeddings')
        ->withJsonSubset(['model' => 'pplx-embed-context-v1-0.6b', 'input' => [['first', 'second']]])
        ->times(1)
        ->replyJson([
            'object' => 'list',
            'data' => [[
                'object' => 'list',
                'index' => 0,
                'data' => [
                    ['object' => 'embedding', 'index' => 0, 'embedding' => base64_encode(pack('c*', 1, 2))],
                    ['object' => 'embedding', 'index' => 1, 'embedding' => base64_encode(pack('c*', 3, 4))],
                ],
            ]],
            'model' => 'pplx-embed-context-v1-0.6b',
            'usage' => ['prompt_tokens' => 4, 'total_tokens' => 4],
        ]);

    $vectors = perplexityEmbeddings($mock)
        ->withModel('pplx-embed-context-v1-0.6b')
        ->withInputs(['first', 'second'])
        ->vectors();

    expect(array_map(fn ($vector) => $vector->values(), $vectors))->toBe([[1.0, 2.0], [3.0, 4.0]]);
});

function perplexityEmbeddings(MockHttpDriver $mock): Embeddings
{
    return Embeddings::fromRuntime(EmbeddingsRuntime::fromConfig(
        EmbeddingsConfig::fromArray([
            'driver' => 'perplexity',
            'apiUrl' => 'https://api.perplexity.ai/v1',
            'apiKey' => 'test',
            'endpoint' => '/embeddings',
            'model' => 'pplx-embed-v1-0.6b',
        ]),
        httpClient: (new HttpClientBuilder)->withDriver($mock)->create(),
    ));
}
