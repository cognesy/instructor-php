<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

/**
 * Pins the `response_format` fragment every OpenAI-family body format emits, across every
 * response-format mode, to a fixture captured before the ResponseFormat closure handlers were
 * removed (instructor-eexl.8).
 *
 * The fixture is the contract. The refactor moved provider variation from Closures injected
 * into a value object into overridable methods on the body formats -- a change that must be
 * invisible on the wire. If a single byte of the emitted fragment moves, this fails, and
 * `git diff` on the fixture is the review artifact.
 *
 * Regenerating the fixture is almost always the wrong fix. It is only correct when a provider
 * genuinely changes its API, and then the diff belongs in the commit that changes it.
 */
const RF_GOLDEN_FIXTURE = __DIR__ . '/../../Fixtures/response-format-fragments.json';

/**
 * Every concrete body format in the OpenAI family, not just the thirteen that injected
 * handlers -- the six that inherit the base unchanged are exactly what would break silently
 * if the base's defaults drifted.
 */
function rfGoldenBodyFormats(): array {
    return [
        'a21' => \Cognesy\Polyglot\Inference\Drivers\A21\A21BodyFormat::class,
        'cerebras' => \Cognesy\Polyglot\Inference\Drivers\Cerebras\CerebrasBodyFormat::class,
        'cohere' => \Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2BodyFormat::class,
        'deepseek' => \Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekBodyFormat::class,
        'fireworks' => \Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksBodyFormat::class,
        'gemini-oai' => \Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIBodyFormat::class,
        'glm' => \Cognesy\Polyglot\Inference\Drivers\Glm\GlmBodyFormat::class,
        'groq' => \Cognesy\Polyglot\Inference\Drivers\Groq\GroqBodyFormat::class,
        'huggingface' => \Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceBodyFormat::class,
        'inception' => \Cognesy\Polyglot\Inference\Drivers\Inception\InceptionBodyFormat::class,
        'meta' => \Cognesy\Polyglot\Inference\Drivers\Meta\MetaBodyFormat::class,
        'minimaxi' => \Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiBodyFormat::class,
        'mistral' => \Cognesy\Polyglot\Inference\Drivers\Mistral\MistralBodyFormat::class,
        'openai' => OpenAIBodyFormat::class,
        'openai-compatible' => \Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat::class,
        'openrouter' => \Cognesy\Polyglot\Inference\Drivers\OpenRouter\OpenRouterBodyFormat::class,
        'perplexity' => \Cognesy\Polyglot\Inference\Drivers\Perplexity\PerplexityBodyFormat::class,
        'qwen' => \Cognesy\Polyglot\Inference\Drivers\Qwen\QwenBodyFormat::class,
        'sambanova' => \Cognesy\Polyglot\Inference\Drivers\SambaNova\SambaNovaBodyFormat::class,
    ];
}

/**
 * Carries every construct a provider transform touches: the two keys OpenAI strips
 * (`x-title`, `x-php-class`), the two more Cohere strips (`additionalProperties`,
 * `nullable`), integers Minimaxi rewrites to `number`, nested `$defs`, and array `items`.
 * A driver-specific schema transform cannot pass unnoticed through this.
 */
function rfGoldenSchema(): array {
    return [
        'type' => 'object',
        'x-title' => 'User',
        'x-php-class' => 'App\\User',
        'additionalProperties' => false,
        'properties' => [
            'name' => ['type' => 'string', 'x-title' => 'Name'],
            'age' => ['type' => 'integer', 'nullable' => true],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'x-php-class' => 'App\\Tag']],
            'role' => ['$ref' => '#/$defs/Role'],
        ],
        'required' => ['name'],
        '$defs' => [
            'Role' => [
                'type' => 'object',
                'x-title' => 'Role',
                'properties' => ['level' => ['type' => 'integer']],
            ],
        ],
    ];
}

function rfGoldenCases(): array {
    return [
        'text' => ResponseFormat::text(),
        'json_object' => ResponseFormat::jsonObject(),
        // JSON mode WITH a schema -- how Instructor actually calls it, and the only case that
        // reaches the schema-bearing json_object paths (Cohere, Meta, Minimaxi, Perplexity).
        'json_object_with_schema' => new ResponseFormat(
            type: 'json_object',
            schema: rfGoldenSchema(),
            name: 'user',
            strict: true,
        ),
        'json_schema' => ResponseFormat::jsonSchema(schema: rfGoldenSchema(), name: 'user', strict: false),
    ];
}

function rfGoldenFragment(string $driver, string $bodyFormatClass, ResponseFormat $responseFormat): mixed {
    $config = new LLMConfig(
        apiUrl: 'https://example.test/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'test-model',
        driver: $driver,
    );
    $bodyFormat = new $bodyFormatClass($config, new OpenAIMessageFormat());

    $body = $bodyFormat->toRequestBody(new InferenceRequest(
        messages: Messages::fromAny([['role' => 'user', 'content' => 'Hi']]),
        model: 'test-model',
        responseFormat: $responseFormat,
    ));

    return $body['response_format'] ?? null;
}

it('emits the pinned response_format fragment for every body format and mode', function () {
    $golden = json_decode((string) file_get_contents(RF_GOLDEN_FIXTURE), true, 512, JSON_THROW_ON_ERROR);

    $actual = [];
    foreach (rfGoldenBodyFormats() as $driver => $class) {
        foreach (rfGoldenCases() as $case => $responseFormat) {
            $actual["{$driver}/{$case}"] = rfGoldenFragment($driver, $class, $responseFormat);
        }
    }

    // Compared as one map rather than key by key: a whole-map comparison reports every drifted
    // driver in a single run, instead of stopping at the first.
    expect($actual)->toBe($golden);
})->group('response-format');

it('covers every body format in the OpenAI family', function () {
    // Guards the fixture against the failure mode it cannot see itself: a new provider whose
    // body format nobody added here would pass by simply not being tested.
    $found = [];
    $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        __DIR__ . '/../../../src/Inference/Drivers',
        FilesystemIterator::SKIP_DOTS,
    ));
    foreach ($dir as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), 'BodyFormat.php')) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (!preg_match('/^namespace\s+([^;]+);/m', $source, $ns)
            || !preg_match('/^(?:final\s+)?class\s+(\w+)/m', $source, $cls)) {
            continue;
        }
        $fqcn = $ns[1] . '\\' . $cls[1];
        if (is_subclass_of($fqcn, OpenAIBodyFormat::class) || $fqcn === OpenAIBodyFormat::class) {
            $found[] = $fqcn;
        }
    }

    sort($found);
    $covered = array_values(rfGoldenBodyFormats());
    sort($covered);

    expect($found)->toBe($covered);
})->group('response-format');

it('keeps every driver in the fixture distinguishable from the base default', function () {
    // The fixture would still pass if a refactor collapsed every provider onto the OpenAI
    // default, PROVIDED the fixture had been regenerated. This asserts the variation the
    // handlers existed to express is really still there, independently of the fixture.
    $golden = json_decode((string) file_get_contents(RF_GOLDEN_FIXTURE), true, 512, JSON_THROW_ON_ERROR);

    // Falls back to json_object where the base emits json_schema.
    expect($golden['a21/json_schema']['type'])->toBe('json_object')
        ->and($golden['deepseek/json_schema']['type'])->toBe('json_object')
        ->and($golden['gemini-oai/json_schema']['type'])->toBe('json_object')
        ->and($golden['sambanova/json_schema']['type'])->toBe('json_object');

    // Escalates json_object to json_schema where the base emits a bare json_object.
    expect($golden['meta/json_object']['type'])->toBe('json_schema')
        ->and($golden['minimaxi/json_object']['type'])->toBe('json_schema')
        ->and($golden['perplexity/json_object']['type'])->toBe('json_schema');

    // Distinct payload keys: schema under `schema`, under `value`, and without name/strict.
    expect($golden['cohere/json_schema'])->toHaveKey('schema')
        ->and($golden['fireworks/json_schema'])->toHaveKey('schema')
        ->and($golden['huggingface/json_schema'])->toHaveKey('value')
        ->and(array_keys($golden['perplexity/json_schema']['json_schema']))->toBe(['schema'])
        ->and(array_keys($golden['minimaxi/json_schema']['json_schema']))->toBe(['name', 'schema']);

    // Minimaxi rewrites integer to number; nobody else does.
    expect($golden['minimaxi/json_schema']['json_schema']['schema']['properties']['age']['type'])->toBe('number')
        ->and($golden['openai/json_schema']['json_schema']['schema']['properties']['age']['type'])->toBe('integer');

    // Cohere strips two more keys than the base and makes every property required.
    expect($golden['cohere/json_schema']['schema'])->not->toHaveKey('additionalProperties')
        ->and($golden['cohere/json_schema']['schema']['required'])->toBe(['name', 'age', 'tags', 'role'])
        ->and($golden['openai/json_schema']['json_schema']['schema']['required'])->toBe(['name']);
})->group('response-format');
