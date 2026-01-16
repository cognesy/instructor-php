<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekDriver;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqDriver;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralDriver;
use Cognesy\Polyglot\Inference\Drivers\Perplexity\PerplexityDriver;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2Driver;
use Cognesy\Polyglot\Inference\Drivers\OpenRouter\OpenRouterDriver;
use Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksDriver;
use Cognesy\Polyglot\Inference\Drivers\SambaNova\SambaNovaDriver;
use Cognesy\Polyglot\Inference\Drivers\Cerebras\CerebrasDriver;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIDriver;
use Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceDriver;
use Cognesy\Polyglot\Inference\Drivers\A21\A21Driver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

beforeEach(function () {
    $this->events = new EventDispatcher();
    $this->httpClient = (new HttpClientBuilder())
        ->withDriver(new MockHttpDriver())
        ->create();
});

describe('OpenAIDriver capabilities', function () {

    it('supports all output modes', function () {
        $config = LLMConfig::fromArray(['driver' => 'openai', 'model' => 'gpt-4o']);
        $driver = new OpenAIDriver($config, $this->httpClient, $this->events);

        $caps = $driver->capabilities();

        expect($caps->supportsOutputMode(OutputMode::Tools))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::JsonSchema))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::Json))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::MdJson))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::Text))->toBeTrue();
    });

    it('supports streaming', function () {
        $config = LLMConfig::fromArray(['driver' => 'openai', 'model' => 'gpt-4o']);
        $driver = new OpenAIDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsStreaming())->toBeTrue();
    });

    it('supports tool calling', function () {
        $config = LLMConfig::fromArray(['driver' => 'openai', 'model' => 'gpt-4o']);
        $driver = new OpenAIDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsToolCalling())->toBeTrue();
    });

    it('supports response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'openai', 'model' => 'gpt-4o']);
        $driver = new OpenAIDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeTrue();
    });

});

describe('AnthropicDriver capabilities', function () {

    it('does not support JsonSchema output mode', function () {
        $config = LLMConfig::fromArray(['driver' => 'anthropic', 'model' => 'claude-3-opus']);
        $driver = new AnthropicDriver($config, $this->httpClient, $this->events);

        $caps = $driver->capabilities();

        expect($caps->supportsOutputMode(OutputMode::Tools))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::MdJson))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::Text))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::JsonSchema))->toBeFalse();
        expect($caps->supportsOutputMode(OutputMode::Json))->toBeFalse();
    });

    it('does not support native JSON schema', function () {
        $config = LLMConfig::fromArray(['driver' => 'anthropic', 'model' => 'claude-3-opus']);
        $driver = new AnthropicDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsJsonSchema())->toBeFalse();
    });

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'anthropic', 'model' => 'claude-3-opus']);
        $driver = new AnthropicDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('supports tool calling', function () {
        $config = LLMConfig::fromArray(['driver' => 'anthropic', 'model' => 'claude-3-opus']);
        $driver = new AnthropicDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsToolCalling())->toBeTrue();
    });

});

describe('DeepseekDriver model-specific capabilities', function () {

    it('supports tools for chat models', function () {
        $config = LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-chat']);
        $driver = new DeepseekDriver($config, $this->httpClient, $this->events);

        $caps = $driver->capabilities();

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsJsonSchema())->toBeTrue();
    });

    it('disables tools for reasoner models via config', function () {
        $config = LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-reasoner']);
        $driver = new DeepseekDriver($config, $this->httpClient, $this->events);

        $caps = $driver->capabilities();

        expect($caps->supportsToolCalling())->toBeFalse();
        expect($caps->supportsJsonSchema())->toBeFalse();
    });

    it('disables tools for reasoner models via parameter override', function () {
        $config = LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-chat']);
        $driver = new DeepseekDriver($config, $this->httpClient, $this->events);

        // Config says chat, but we query for reasoner specifically
        $caps = $driver->capabilities('deepseek-reasoner');

        expect($caps->supportsToolCalling())->toBeFalse();
        expect($caps->supportsJsonSchema())->toBeFalse();
    });

    it('allows model parameter to override config model', function () {
        $config = LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-reasoner']);
        $driver = new DeepseekDriver($config, $this->httpClient, $this->events);

        // Config says reasoner, but we query for chat specifically
        $caps = $driver->capabilities('deepseek-chat');

        expect($caps->supportsToolCalling())->toBeTrue();
        expect($caps->supportsJsonSchema())->toBeTrue();
    });

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'deepseek', 'model' => 'deepseek-chat']);
        $driver = new DeepseekDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('GeminiDriver capabilities', function () {

    it('supports all output modes', function () {
        $config = LLMConfig::fromArray(['driver' => 'gemini', 'model' => 'gemini-pro']);
        $driver = new GeminiDriver($config, $this->httpClient, $this->events);

        $caps = $driver->capabilities();

        expect($caps->supportsOutputMode(OutputMode::Tools))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::JsonSchema))->toBeTrue();
        expect($caps->supportsOutputMode(OutputMode::Json))->toBeTrue();
    });

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'gemini', 'model' => 'gemini-pro']);
        $driver = new GeminiDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('GroqDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'groq', 'model' => 'llama-3']);
        $driver = new GroqDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('MistralDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'mistral', 'model' => 'mistral-large']);
        $driver = new MistralDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('PerplexityDriver capabilities', function () {

    it('does not support tool calling', function () {
        $config = LLMConfig::fromArray(['driver' => 'perplexity', 'model' => 'sonar']);
        $driver = new PerplexityDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsToolCalling())->toBeFalse();
    });

    it('does not support Tools output mode', function () {
        $config = LLMConfig::fromArray(['driver' => 'perplexity', 'model' => 'sonar']);
        $driver = new PerplexityDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsOutputMode(OutputMode::Tools))->toBeFalse();
    });

});

describe('CohereV2Driver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'cohere2', 'model' => 'command-r']);
        $driver = new CohereV2Driver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('supports tool calling and JSON schema', function () {
        $config = LLMConfig::fromArray(['driver' => 'cohere2', 'model' => 'command-r']);
        $driver = new CohereV2Driver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsToolCalling())->toBeTrue();
        expect($driver->capabilities()->supportsJsonSchema())->toBeTrue();
    });

});

describe('OpenRouterDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'openrouter', 'model' => 'openai/gpt-4']);
        $driver = new OpenRouterDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('FireworksDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'fireworks', 'model' => 'llama-v3']);
        $driver = new FireworksDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('SambaNovaDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'sambanova', 'model' => 'llama-3']);
        $driver = new SambaNovaDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('does not support native JSON schema', function () {
        $config = LLMConfig::fromArray(['driver' => 'sambanova', 'model' => 'llama-3']);
        $driver = new SambaNovaDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsJsonSchema())->toBeFalse();
    });

});

describe('CerebrasDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'cerebras', 'model' => 'llama3.1-8b']);
        $driver = new CerebrasDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('GeminiOAIDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'gemini-oai', 'model' => 'gemini-1.5-flash']);
        $driver = new GeminiOAIDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

    it('does not support native JSON schema', function () {
        $config = LLMConfig::fromArray(['driver' => 'gemini-oai', 'model' => 'gemini-1.5-flash']);
        $driver = new GeminiOAIDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsJsonSchema())->toBeFalse();
    });

});

describe('HuggingFaceDriver capabilities', function () {

    it('does not support response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'huggingface', 'model' => 'mistralai/Mistral-7B']);
        $driver = new HuggingFaceDriver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeFalse();
    });

});

describe('A21Driver capabilities', function () {

    it('does not support native JSON schema', function () {
        $config = LLMConfig::fromArray(['driver' => 'a21', 'model' => 'jamba-1.5']);
        $driver = new A21Driver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsJsonSchema())->toBeFalse();
    });

    it('supports response format with tools', function () {
        $config = LLMConfig::fromArray(['driver' => 'a21', 'model' => 'jamba-1.5']);
        $driver = new A21Driver($config, $this->httpClient, $this->events);

        expect($driver->capabilities()->supportsResponseFormatWithTools())->toBeTrue();
    });

});
