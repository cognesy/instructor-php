<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Messages\Messages;

/**
 * P3 pilot verification (research/v2-cleanup-plan): golden request bodies for
 * MistralBodyFormat across representative request shapes. Captured BEFORE the
 * rebase onto OpenAICompatibleBodyFormat; the rebase must keep them identical.
 *
 * Regenerate intentionally:
 *   MISTRAL_BODY_SNAPSHOT_UPDATE=1 vendor/bin/pest .../MistralBodyFormatGoldenTest.php
 */

function mistralBodyFixture(): MistralBodyFormat {
    $config = LLMConfig::fromArray([
        'model' => 'mistral-small-latest',
        'maxTokens' => 1024,
        'driver' => 'mistral',
    ]);
    return new MistralBodyFormat($config, new OpenAIMessageFormat());
}

function mistralBodyCases(): array {
    $messages = Messages::fromArray([['role' => 'user', 'content' => 'Extract the data.']]);
    $tools = \Cognesy\Polyglot\Inference\Data\ToolDefinitions::fromArray([[
        'type' => 'function',
        'function' => [
            'name' => 'extract',
            'parameters' => [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
                'x-php-class' => 'ShouldBeRemoved',
            ],
        ],
    ]]);
    $jsonSchemaFormat = \Cognesy\Polyglot\Inference\Data\ResponseFormat::fromArray([
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'thing',
            'schema' => ['type' => 'object', 'properties' => ['a' => ['type' => 'integer']]],
            'strict' => true,
        ],
    ]);

    return [
        'plain' => new InferenceRequest(messages: $messages),
        'streaming-with-options' => new InferenceRequest(
            messages: $messages,
            options: ['stream' => true, 'temperature' => 0.5, 'parallel_tool_calls' => true],
        ),
        'json-schema' => new InferenceRequest(
            messages: $messages,
            responseFormat: $jsonSchemaFormat,
        ),
        'tools' => new InferenceRequest(
            messages: $messages,
            tools: $tools,
        ),
        'max-tokens-passthrough' => new InferenceRequest(
            messages: $messages,
            options: ['max_tokens' => 512],
        ),
    ];
}

it('locks Mistral request bodies against the golden fixture', function () {
    $format = mistralBodyFixture();
    $actual = [];
    foreach (mistralBodyCases() as $name => $request) {
        $actual[$name] = $format->toRequestBody($request);
    }

    $path = __DIR__ . '/Fixtures/mistral-request-bodies.json';

    if (getenv('MISTRAL_BODY_SNAPSHOT_UPDATE') === '1' || !file_exists($path)) {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        expect(file_exists($path))->toBeTrue();
        return;
    }

    $expected = json_decode(file_get_contents($path), true);
    expect($actual)->toBe($expected, 'Mistral request body drift — the rebase must be byte-identical.');
});
