<?php

declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

it('carries explicit TTL and automatic cache options from the structured facade to HTTP', function () {
    $mock = new MockHttpDriver();
    $mock->on()->post('https://api.anthropic.com/v1/messages')
        ->withJsonSubset([
            'cache_control' => ['type' => 'ephemeral'],
            'system' => [['cache_control' => ['type' => 'ephemeral', 'ttl' => '1h']]],
        ])
        ->replyJson([
            'content' => [['type' => 'text', 'text' => '{"name":"Jane"}']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'cache_creation_input_tokens' => 4096, 'cache_read_input_tokens' => 0],
        ]);
    $runtime = StructuredOutputRuntime::fromConfig(
        new LLMConfig(driver: 'anthropic', apiUrl: 'https://api.anthropic.com/v1', endpoint: '/messages', model: 'claude-haiku-4-5', apiKey: 'test'),
        httpClient: (new HttpClientBuilder())->withDriver($mock)->create(),
        structuredConfig: new StructuredOutputConfig(outputMode: OutputMode::MdJson),
    );
    $result = (new StructuredOutput($runtime))
        ->withCachedContext(system: 'Stable instructions', ttl: '1h')
        ->withOptions(['cache_control' => ['type' => 'ephemeral']])
        ->withMessages('Jane')
        ->withResponseJsonSchema(['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']])
        ->intoArray()->get();
    expect($result)->toBe(['name' => 'Jane']);
});

it('preserves structured cached TTL through hydration and rejects invalid values', function () {
    $cached = new CachedContext(system: 'Stable', ttl: '1h');
    expect(CachedContext::fromArray($cached->toArray())->ttl())->toBe('1h')
        ->and(fn() => new CachedContext(ttl: '24h'))->toThrow(InvalidArgumentException::class);
});
