<?php

declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Embeddings\Drivers\Perplexity\PerplexityBodyFormat;
use Cognesy\Polyglot\Embeddings\Drivers\Perplexity\PerplexityRequestAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\Perplexity\PerplexityResponseAdapter;

const PERPLEXITY_EMBEDDINGS_FIXTURES = __DIR__.'/../../../../Fixtures/Embeddings';

it('sends standard models a flat input list to the embeddings endpoint', function (): void {
    $http = perplexityEmbeddingsAdapter()->toHttpClientRequest(new EmbeddingsRequest(
        input: ['hello world', 'goodbye'],
        options: ['dimensions' => 128],
    ));

    expect($http->url())->toBe('https://api.perplexity.ai/v1/embeddings')
        ->and($http->headers()['Authorization'])->toBe('Bearer synthetic-test-key')
        ->and($http->body()->toArray())->toBe([
            'dimensions' => 128,
            'input' => ['hello world', 'goodbye'],
            'model' => 'pplx-embed-v1-0.6b',
            'encoding_format' => 'base64_int8',
        ]);
});

it('sends contextualized models the inputs as chunks of one document', function (): void {
    $http = perplexityEmbeddingsAdapter()->toHttpClientRequest(new EmbeddingsRequest(
        input: ['chunk one', 'chunk two'],
        model: 'pplx-embed-context-v1-4b',
    ));

    expect($http->url())->toBe('https://api.perplexity.ai/v1/contextualizedembeddings')
        ->and($http->body()->toArray()['input'])->toBe([['chunk one', 'chunk two']])
        ->and($http->body()->toArray()['model'])->toBe('pplx-embed-context-v1-4b');
});

it('rejects encodings other than base64 int8', function (): void {
    $request = new EmbeddingsRequest(input: ['a'], options: ['encoding_format' => 'base64_binary']);

    expect(fn () => perplexityEmbeddingsAdapter()->toHttpClientRequest($request))
        ->toThrow(InvalidArgumentException::class, "only the 'base64_int8' encoding format");
});

it('decodes a recorded standard response into signed int8 vectors', function (): void {
    $data = perplexityEmbeddingsFixture('perplexity-embeddings-response.json');
    $response = (new PerplexityResponseAdapter(new OpenAIUsageFormat))->fromResponse($data);
    $vectors = $response->vectors();
    $expected = array_map('floatval', array_values(unpack('c*', base64_decode($data['data'][1]['embedding']))));

    expect($vectors)->toHaveCount(2)
        ->and($vectors[0]->values())->toHaveCount(128)
        ->and($vectors[1]->id())->toBe(1)
        ->and($vectors[1]->values())->toBe($expected)
        ->and(min($vectors[0]->values()))->toBeGreaterThanOrEqual(-128.0)
        ->and(max($vectors[0]->values()))->toBeLessThanOrEqual(127.0)
        ->and($response->usage()->input())->toBe(4);
});

it('flattens a recorded contextualized response in chunk order', function (): void {
    $data = perplexityEmbeddingsFixture('perplexity-contextualized-response.json');
    $data['data'][0]['data'] = array_reverse($data['data'][0]['data']);
    $vectors = (new PerplexityResponseAdapter(new OpenAIUsageFormat))->fromResponse($data)->vectors();
    $firstChunk = array_values(array_filter($data['data'][0]['data'], fn (array $c): bool => $c['index'] === 0))[0];

    expect($vectors)->toHaveCount(2)
        ->and(array_map(fn ($v) => $v->id(), $vectors))->toBe([0, 1])
        ->and($vectors[0]->values())->toBe(array_map('floatval', array_values(unpack('c*', base64_decode($firstChunk['embedding'])))));
});

it('fails on a non-base64 embedding', function (): void {
    $data = ['data' => [['index' => 0, 'embedding' => [0.1, 0.2]]], 'usage' => ['prompt_tokens' => 1]];

    expect(fn () => (new PerplexityResponseAdapter(new OpenAIUsageFormat))->fromResponse($data))
        ->toThrow(RuntimeException::class, 'base64 int8');
});

function perplexityEmbeddingsAdapter(): PerplexityRequestAdapter
{
    $config = EmbeddingsConfig::fromArray([
        'driver' => 'perplexity',
        'apiUrl' => 'https://api.perplexity.ai/v1/',
        'apiKey' => 'synthetic-test-key',
        'endpoint' => '/embeddings',
        'model' => 'pplx-embed-v1-0.6b',
    ]);

    return new PerplexityRequestAdapter($config, new PerplexityBodyFormat($config));
}

function perplexityEmbeddingsFixture(string $name): array
{
    $json = file_get_contents(PERPLEXITY_EMBEDDINGS_FIXTURES.'/'.$name);
    if (! is_string($json)) {
        throw new RuntimeException("Fixture {$name} cannot be read.");
    }

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}
