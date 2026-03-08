<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\A21\A21Driver;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\Inference\Drivers\Cerebras\CerebrasDriver;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2Driver;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekDriver;
use Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksDriver;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmDriver;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqDriver;
use Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceDriver;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenRouter\OpenRouterDriver;
use Cognesy\Polyglot\Inference\Drivers\Perplexity\PerplexityDriver;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenDriver;
use Cognesy\Polyglot\Inference\Drivers\SambaNova\SambaNovaDriver;

beforeEach(function () {
    $this->events = new EventDispatcher();
    $this->httpClient = (new HttpClientBuilder())
        ->withDriver(new MockHttpDriver())
        ->create();
});

describe('OpenAIDriver capabilities', function () {
    it('supports native response formats, tools, and streaming', function () {
        $driver = new OpenAIDriver(
            LLMConfig::fromArray(['driver' => 'openai', 'model' => 'gpt-4o']),
            $this->httpClient,
            $this->events,
        );

        $caps = $driver->capabilities();

        expect($caps->supportsStreaming())->toBeTrue();
        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeTrue();
    });
});

describe('AnthropicDriver capabilities', function () {
    it('supports tools but no native response formats', function () {
        $driver = new AnthropicDriver(
            LLMConfig::fromArray(['driver' => 'anthropic', 'model' => 'claude-3-opus']),
            $this->httpClient,
            $this->events,
        );

        $caps = $driver->capabilities();

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeFalse();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });
});

describe('DeepseekDriver model-specific capabilities', function () {
    it('supports native response formats and tools for chat models', function () {
        $driver = new DeepseekDriver(
            LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-chat']),
            $this->httpClient,
            $this->events,
        );

        $caps = $driver->capabilities();

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsToolChoice())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
    });

    it('disables tools and JSON schema for reasoner models via config', function () {
        $driver = new DeepseekDriver(
            LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-reasoner']),
            $this->httpClient,
            $this->events,
        );

        $caps = $driver->capabilities();

        expect($caps->supportsToolCalling())->toBeFalse();
        expect($caps->supportsToolChoice())->toBeFalse();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
    });

    it('lets the model parameter override the configured model', function () {
        $driver = new DeepseekDriver(
            LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-chat']),
            $this->httpClient,
            $this->events,
        );

        $caps = $driver->capabilities('deepseek-reasoner');

        expect($caps->supportsToolCalling())->toBeFalse();
        expect($caps->supportsToolChoice())->toBeFalse();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
    });

    it('does not support combining response format with tools', function () {
        $driver = new DeepseekDriver(
            LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-chat']),
            $this->httpClient,
            $this->events,
        );

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });
});

describe('Gemini-family and OpenAI-compatible drivers', function () {
    it('Gemini supports native response formats but not response_format with tools', function () {
        $caps = (new GeminiDriver(
            LLMConfig::fromArray(['driver' => 'gemini', 'model' => 'gemini-pro']),
            $this->httpClient,
            $this->events,
        ))->capabilities();

        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('Gemini OAI supports JSON object but not JSON schema', function () {
        $caps = (new GeminiOAIDriver(
            LLMConfig::fromArray(['driver' => 'gemini-oai', 'model' => 'gemini-1.5-flash']),
            $this->httpClient,
            $this->events,
        ))->capabilities();

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('A21 supports JSON object but not JSON schema', function () {
        $caps = (new A21Driver(
            LLMConfig::fromArray(['driver' => 'a21', 'model' => 'jamba-1.5']),
            $this->httpClient,
            $this->events,
        ))->capabilities();

        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeTrue();
    });

    it('SambaNova supports JSON object but not JSON schema', function () {
        $caps = (new SambaNovaDriver(
            LLMConfig::fromArray(['driver' => 'sambanova', 'model' => 'llama-3']),
            $this->httpClient,
            $this->events,
        ))->capabilities();

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeFalse();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });
});

describe('PerplexityDriver capabilities', function () {
    it('supports native response formats but no tools', function () {
        $caps = (new PerplexityDriver(
            LLMConfig::fromArray(['driver' => 'perplexity', 'model' => 'sonar']),
            $this->httpClient,
            $this->events,
        ))->capabilities();

        expect($caps->supportsToolCalling())->toBeFalse();
        expect($caps->supportsToolChoice())->toBeFalse();
        expect($caps->supportsResponseFormatJsonObject())->toBeTrue();
        expect($caps->supportsResponseFormatJsonSchema())->toBeTrue();
        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    });
});

describe('Drivers that do not combine response_format with tools', function () {
    it('reports the expected compatibility limits', function (string $driverClass, string $driverName, string $model) {
        $caps = (new $driverClass(
            LLMConfig::fromArray(['driver' => $driverName, 'model' => $model]),
            $this->httpClient,
            $this->events,
        ))->capabilities();

        expect($caps->supportsResponseFormatWithTools())->toBeFalse();
    })->with([
        [QwenDriver::class, 'qwen', 'qwen3-max-preview'],
        [GlmDriver::class, 'glm', 'glm-4.5'],
        [GroqDriver::class, 'groq', 'llama-3'],
        [MistralDriver::class, 'mistral', 'mistral-large'],
        [CohereV2Driver::class, 'cohere2', 'command-r'],
        [OpenRouterDriver::class, 'openrouter', 'openai/gpt-4'],
        [FireworksDriver::class, 'fireworks', 'llama-v3'],
        [CerebrasDriver::class, 'cerebras', 'llama3.1-8b'],
        [HuggingFaceDriver::class, 'huggingface', 'mistralai/Mistral-7B'],
    ]);
});
