<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

/**
 * The response-side counterpart to DriverGoldenRequestTest: what each bundled provider makes
 * OF a payload, rather than what it sends.
 *
 * WHY THIS EXISTS. instructor-eexl.9 turned each driver into a row of class names, two of
 * which -- `responseAdapter` and `usageFormat` -- are response-side and therefore invisible to
 * the request golden. Mutation-checking found exactly how invisible: making
 * InferenceDriverSpec ignore `usageFormat` entirely, so every provider parsed usage with
 * OpenAIUsageFormat, left all 4458 tests in the repository passing. `GroqUsageFormat` is
 * wired by one spec and read by nothing; deleting that one line was free.
 *
 * `responseAdapter` fared better but not well: only InferenceDeepseekReasoningContentTest
 * noticed, and only for Deepseek. Glm and Qwen wire the same shared adapter and nothing
 * asserted it, nor that Minimaxi wires its own.
 *
 * THE PAYLOAD IS A DELIBERATE CHIMERA. It carries, in one body, every key that any bundled
 * response adapter or usage format reads and that any other one ignores -- so a provider's
 * capture differs from its neighbour's precisely when its wiring does. Feeding each provider
 * a payload it would never really receive is the point: a realistic payload is one that every
 * OpenAI-family adapter parses identically, which is a test that cannot fail.
 *
 * Providers with bespoke drivers (Anthropic, Gemini, Cohere, ...) do not speak this shape and
 * mostly return empty or throw. That is pinned too -- it is what "this provider is NOT wired
 * to the OpenAI adapter" looks like.
 */
function driverGoldenResponseConfig(string $providerName): LLMConfig {
    return new LLMConfig(
        apiUrl: 'https://api.golden.test/v1',
        apiKey: 'GOLDEN-KEY',
        endpoint: '/chat/completions',
        model: 'test-model',
        driver: $providerName,
    );
}

function driverGoldenResponsePayloads(): array {
    return [
        // Every discriminating key at once.
        //
        //   reasoning_content  -- only OpenAICompatibleReasoningAdapter lifts it out of the
        //                         message (Deepseek, Glm, Qwen).
        //   x_groq.usage       -- only GroqUsageFormat prefers it, and it is set to values
        //                         that DISAGREE with `usage` so preferring it is observable.
        //   *_tokens_details   -- only OpenAIUsageFormat reads cached/reasoning token details,
        //                         so a provider that lost its OpenAI usage format shows zeros.
        //   base_resp ok       -- present and benign, so this variant does not trip Minimaxi.
        'discriminating' => [
            'id' => 'resp-golden',
            'model' => 'test-model',
            'choices' => [[
                'index' => 0,
                'finish_reason' => 'tool_calls',
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Paris',
                    'reasoning_content' => 'Recalled the capital of France.',
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => ['name' => 'get_weather', 'arguments' => '{"city":"NYC"}'],
                    ]],
                ],
            ]],
            'usage' => [
                'prompt_tokens' => 11,
                'completion_tokens' => 22,
                'prompt_tokens_details' => ['cached_tokens' => 3],
                'completion_tokens_details' => ['reasoning_tokens' => 7],
            ],
            'x_groq' => ['usage' => ['prompt_tokens' => 111, 'completion_tokens' => 222]],
            'base_resp' => ['status_code' => 0, 'status_msg' => ''],
        ],

        // The same body with a non-zero base_resp. MinimaxiResponseAdapter is the only one
        // that inspects it, so this is the single variant on which Minimaxi may not look like
        // plain OpenAI -- and the only way to pin that its spec keeps its own response adapter.
        'provider_error' => [
            'id' => 'resp-golden',
            'model' => 'test-model',
            'choices' => [[
                'index' => 0,
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => 'Paris'],
            ]],
            'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 22],
            'base_resp' => ['status_code' => 1004, 'status_msg' => 'invalid api key'],
        ],
    ];
}

function driverGoldenResponseCapture(string $providerName, array $payload): array {
    $mock = new MockHttpDriver();
    $mock->addResponse(MockHttpResponseFactory::success(
        body: (string) json_encode($payload),
    ));

    $driver = BundledInferenceDrivers::registry()->makeDriver(
        $providerName,
        driverGoldenResponseConfig($providerName),
        (new HttpClientBuilder())->withDriver($mock)->create(),
        new EventDispatcher(),
    );

    $request = new InferenceRequest(
        messages: Messages::fromString('Q?'),
        model: 'test-model',
        options: ['stream' => false],
    );

    try {
        $response = $driver->makeResponseFor($request);
    } catch (\Throwable $e) {
        // Throwing IS the behaviour for Minimaxi's error variant and for the bespoke drivers
        // handed a shape they do not speak. The class and message are pinned rather than
        // swallowed, because "which adapter rejected this" is exactly the wiring under test.
        return ['threw' => $e::class . ': ' . $e->getMessage()];
    }

    $usage = $response->usage();

    return [
        'threw' => null,
        'content' => $response->content(),
        'reasoningContent' => $response->reasoningContent(),
        'finishReason' => $response->finishReason()->value,
        // id() returns a ToolCallId value object, which JSON-encodes as {"value": ...} and
        // comes back from the fixture as an array -- so the identity comparison would fail
        // against a snapshot that is in fact correct. Flattened to a string at capture time.
        'toolCalls' => array_map(
            static fn($call) => [
                'id' => (string) ($call->id()?->value ?? ''),
                'name' => $call->name(),
                'args' => $call->args(),
            ],
            $response->toolCalls()->all(),
        ),
        'usage' => [
            'input' => $usage->inputTokens,
            'output' => $usage->outputTokens,
            'cacheWrite' => $usage->cacheWriteTokens,
            'cacheRead' => $usage->cacheReadTokens,
            'reasoning' => $usage->reasoningTokens,
        ],
    ];
}

function driverGoldenResponseSnapshot(): array {
    $out = [];
    foreach (BundledInferenceDrivers::registry()->driverNames() as $name) {
        $row = [];
        foreach (driverGoldenResponsePayloads() as $variant => $payload) {
            $row[$variant] = driverGoldenResponseCapture($name, $payload);
        }
        $out[$name] = $row;
    }
    ksort($out);
    return $out;
}

function driverGoldenResponseFixture(): array {
    $path = __DIR__ . '/../../Fixtures/driver-golden-responses.json';
    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

it('parses the pinned response for every bundled provider', function () {
    $snapshot = driverGoldenResponseSnapshot();
    $fixture = driverGoldenResponseFixture();

    // Same shape as the request golden, for the same measured reason: the drifted-key list
    // arrives instantly and says whether one provider moved or all of them did.
    $drifted = [];
    foreach ($fixture as $provider => $row) {
        foreach ($row as $variant => $capture) {
            if (($snapshot[$provider][$variant] ?? null) !== $capture) {
                $drifted[] = "{$provider}/{$variant}";
            }
        }
    }
    expect($drifted)->toBe([]);

    if ($drifted !== []) {
        [$provider, $variant] = explode('/', $drifted[0], 2);
        expect($snapshot[$provider][$variant] ?? null)->toBe($fixture[$provider][$variant]);
    }

    expect(array_keys($snapshot))->toBe(array_keys($fixture));
})->group('driver-golden');

it('keeps the response-side wiring of the four providers that do not use the OpenAI defaults', function () {
    // The fixture above would happily record a collapsed table as though it had always been
    // so. These four assertions say WHAT makes each of them different, in the test source, so
    // that dropping the corresponding spec field fails here with a readable reason rather than
    // as one more line in a drift list.
    $snapshot = driverGoldenResponseSnapshot();
    $openai = $snapshot['openai']['discriminating'];

    // Groq prefers x_groq.usage (111/222) over usage (11/22). Nothing else does.
    expect($snapshot['groq']['discriminating']['usage']['input'])->toBe(111)
        ->and($snapshot['groq']['discriminating']['usage']['output'])->toBe(222)
        ->and($openai['usage']['input'])->toBe(11);

    // Deepseek, Glm and Qwen wire the shared reasoning adapter; plain OpenAI leaves the field
    // empty. All three are asserted, not just Deepseek -- the other two were unpinned.
    foreach (['deepseek', 'glm', 'qwen'] as $provider) {
        expect($snapshot[$provider]['discriminating']['reasoningContent'])
            ->toBe('Recalled the capital of France.');
    }
    expect($openai['reasoningContent'])->toBe('');

    // Minimaxi alone rejects a payload whose base_resp carries a provider error.
    expect($snapshot['minimaxi']['provider_error']['threw'])
        ->toContain('MiniMaxi API error 1004: invalid api key');
    expect($snapshot['openai']['provider_error']['threw'])->toBeNull();
})->group('driver-golden');
