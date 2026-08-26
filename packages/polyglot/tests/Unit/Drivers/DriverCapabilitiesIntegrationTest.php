<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;

/**
 * Drivers are built through the registry by provider name rather than with `new XxxDriver(...)`.
 *
 * Bundled provider classes no longer exist -- all entries are `InferenceDriverSpec` rows -- so a
 * test that names them would be testing the packaging rather than the behaviour. The provider
 * name is the thing callers actually use and the thing that has to keep answering the same way.
 */
function capabilitiesOf(string $provider, string $model, ?string $forModel = null) {
    $driver = BundledInferenceDrivers::registry()->makeDriver(
        $provider,
        LLMConfig::fromArray(['driver' => $provider, 'model' => $model]),
        (new HttpClientBuilder())->withDriver(new MockHttpDriver())->create(),
        new EventDispatcher(),
    );

    return $driver->capabilities($forModel);
}

describe('openai capabilities', function () {
    it('supports native response formats, tools, and streaming', function () {
        $caps = capabilitiesOf('openai', 'gpt-4o');

        expect($caps->supportsStreaming())->toBeTrue();
        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeTrue();
    });
});

it('describes bundled capabilities without constructing a driver', function () {
    $caps = BundledInferenceDrivers::capabilities('deepseek', 'deepseek-v4-flash');

    expect($caps)->not->toBeNull()
        ->and($caps?->supportsToolCalling())->toBeTrue()
        ->and($caps?->supportsResponseFormatJsonSchema())->toBeFalse()
        ->and(BundledInferenceDrivers::capabilities('not-a-provider'))->toBeNull();
});

describe('anthropic capabilities', function () {
    it('supports tools but no native response formats', function () {
        $caps = capabilitiesOf('anthropic', 'claude-3-opus');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeFalse();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsReasoningEffort())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });
});

describe('DeepSeek V4 capabilities', function () {
    it('supports tools and JSON Output for V4 Flash', function () {
        $caps = capabilitiesOf('deepseek', 'deepseek-v4-flash');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsReasoningEffort())->toBeTrue();
    });

    it('supports the same feature set for V4 Pro', function () {
        $caps = capabilitiesOf('deepseek', 'deepseek-v4-pro');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsReasoningEffort())->toBeTrue();
    });

    it('does not vary by the selected V4 model', function () {
        $caps = capabilitiesOf('deepseek', 'deepseek-v4-flash', forModel: 'deepseek-v4-pro');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
    });

    it('does not support combining response format with tools', function () {
        expect(capabilitiesOf('deepseek', 'deepseek-v4-flash')->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('does not advertise reasoning effort for legacy model identifiers', function () {
        expect(capabilitiesOf('deepseek', 'deepseek-chat')->supportsReasoningEffort())->toBeFalse();
    });
});

describe('Qwen current model capabilities', function () {
    it('supports tools, JSON Object, and JSON Schema on Qwen3.8-Max', function () {
        $caps = capabilitiesOf('qwen', 'qwen3.8-max');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
        expect($caps->supportsReasoningEffort())->toBeTrue();
    });

    it('limits legacy Qwen models to JSON Object', function () {
        $caps = capabilitiesOf('qwen', 'qwen3-max-preview');

        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
        expect($caps->supportsReasoningEffort())->toBeFalse();
    });
});

describe('Gemini-family and OpenAI-compatible drivers', function () {
    it('Gemini supports native response formats but not response_format with tools', function () {
        $caps = capabilitiesOf('gemini', 'gemini-pro');

        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('Gemini OAI supports JSON object but not JSON schema', function () {
        $caps = capabilitiesOf('gemini-oai', 'gemini-1.5-flash');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('A21 supports JSON object but not JSON schema', function () {
        $caps = capabilitiesOf('a21', 'jamba-1.5');

        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeTrue();
    });

    it('SambaNova supports JSON object but not JSON schema', function () {
        $caps = capabilitiesOf('sambanova', 'llama-3');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });
});

describe('perplexity capabilities', function () {
    it('supports native response formats but no tools', function () {
        $caps = capabilitiesOf('perplexity', 'sonar');

        expect($caps->supportsToolCalling())->toBeFalse();
        expect($caps->supportsToolChoice())->toBeFalse();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });
});

describe('Drivers that do not combine response_format with tools', function () {
    it('reports the expected compatibility limits', function (string $driverName, string $model) {
        expect(capabilitiesOf($driverName, $model)->supportsResponseFormatWithTools())->toBeFalse();
    })->with([
        ['qwen', 'qwen3.8-max'],
        ['glm', 'glm-4.5'],
        ['groq', 'llama-3'],
        ['mistral', 'mistral-large'],
        // Was passed as 'cohere2' when the class was constructed directly, where the config's
        // driver field was decorative. Going through the registry means using the real key.
        ['cohere', 'command-r'],
        ['openrouter', 'openai/gpt-4'],
        ['fireworks', 'llama-v3'],
        ['cerebras', 'llama3.1-8b'],
        ['huggingface', 'mistralai/Mistral-7B'],
    ]);
});
