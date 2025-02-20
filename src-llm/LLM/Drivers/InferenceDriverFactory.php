<?php

namespace Cognesy\LLM\LLM\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\LLM\Http\Contracts\CanHandleHttp;
use Cognesy\LLM\LLM\Contracts\CanHandleInference;
use Cognesy\LLM\LLM\Data\LLMConfig;
use Cognesy\LLM\LLM\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\LLM\LLM\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\LLM\LLM\Drivers\Anthropic\AnthropicRequestAdapter;
use Cognesy\LLM\LLM\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\LLM\LLM\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\LLM\LLM\Drivers\Azure\AzureOpenAIRequestAdapter;
use Cognesy\LLM\LLM\Drivers\Cerebras\CerebrasBodyFormat;
use Cognesy\LLM\LLM\Drivers\CohereV1\CohereV1BodyFormat;
use Cognesy\LLM\LLM\Drivers\CohereV1\CohereV1MessageFormat;
use Cognesy\LLM\LLM\Drivers\CohereV1\CohereV1RequestAdapter;
use Cognesy\LLM\LLM\Drivers\CohereV1\CohereV1ResponseAdapter;
use Cognesy\LLM\LLM\Drivers\CohereV1\CohereV1UsageFormat;
use Cognesy\LLM\LLM\Drivers\CohereV2\CohereV2BodyFormat;
use Cognesy\LLM\LLM\Drivers\CohereV2\CohereV2RequestAdapter;
use Cognesy\LLM\LLM\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\LLM\LLM\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\LLM\LLM\Drivers\Deepseek\DeepseekResponseAdapter;
use Cognesy\LLM\LLM\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\LLM\LLM\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\LLM\LLM\Drivers\Gemini\GeminiRequestAdapter;
use Cognesy\LLM\LLM\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\LLM\LLM\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\LLM\LLM\Drivers\GeminiOAI\GeminiOAIBodyFormat;
use Cognesy\LLM\LLM\Drivers\GeminiOAI\GeminiOAIUsageFormat;
use Cognesy\LLM\LLM\Drivers\Groq\GroqUsageFormat;
use Cognesy\LLM\LLM\Drivers\Minimaxi\MinimaxiBodyFormat;
use Cognesy\LLM\LLM\Drivers\Mistral\MistralBodyFormat;
use Cognesy\LLM\LLM\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\LLM\LLM\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\LLM\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\LLM\LLM\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\LLM\LLM\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\LLM\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\LLM\LLM\Drivers\Perplexity\PerplexityBodyFormat;
use Cognesy\LLM\LLM\Drivers\SambaNova\SambaNovaBodyFormat;
use Cognesy\LLM\LLM\Drivers\XAI\XAiMessageFormat;
use Cognesy\LLM\LLM\Enums\LLMProviderType;
use InvalidArgumentException;

/**
 * Factory class for creating inference driver instances based
 * on the specified configuration and provider type.
 */
class InferenceDriverFactory
{
    /**
     * Creates and returns an appropriate driver instance based on the given configuration.
     *
     * @param \Cognesy\LLM\LLM\Data\LLMConfig $config Configuration object specifying the provider type and other necessary settings.
     * @param CanHandleHttp $httpClient An HTTP client instance to handle HTTP requests.
     *
     * @return \Cognesy\LLM\LLM\Contracts\CanHandleInference A driver instance matching the specified provider type.
     * @throws InvalidArgumentException If the provider type is not supported.
     */
    public function make(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return match ($config->providerType) {
            // Tailored drivers
            LLMProviderType::Anthropic => $this->anthropic($config, $httpClient, $events),
            LLMProviderType::Azure => $this->azure($config, $httpClient, $events),
            LLMProviderType::Cerebras => $this->cerebras($config, $httpClient, $events),
            LLMProviderType::CohereV1 => $this->cohereV1($config, $httpClient, $events),
            LLMProviderType::CohereV2 => $this->cohereV2($config, $httpClient, $events),
            LLMProviderType::DeepSeek => $this->deepseek($config, $httpClient, $events),
            LLMProviderType::Gemini => $this->gemini($config, $httpClient, $events),
            LLMProviderType::GeminiOAI => $this->geminiOAI($config, $httpClient, $events),
            LLMProviderType::Groq => $this->groq($config, $httpClient, $events),
            LLMProviderType::Minimaxi => $this->minimaxi($config, $httpClient, $events),
            LLMProviderType::Mistral => $this->mistral($config, $httpClient, $events),
            LLMProviderType::OpenAI => $this->openAI($config, $httpClient, $events),
            LLMProviderType::Perplexity => $this->perplexity($config, $httpClient, $events),
            LLMProviderType::SambaNova => $this->sambaNova($config, $httpClient, $events),
            LLMProviderType::XAi => $this->xAi($config, $httpClient, $events),
            // OpenAI compatible driver for generic OAI providers
            LLMProviderType::A21,
            LLMProviderType::Fireworks,
            LLMProviderType::Moonshot,
            LLMProviderType::Ollama,
            LLMProviderType::OpenAICompatible,
            LLMProviderType::OpenRouter,
            LLMProviderType::Together => $this->openAICompatible($config, $httpClient, $events),
            default => throw new InvalidArgumentException("Client not supported: {$config->providerType->value}"),
        };
    }

    public function anthropic(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new AnthropicRequestAdapter(
                $config,
                new AnthropicBodyFormat($config, new AnthropicMessageFormat())
            ),
            new AnthropicResponseAdapter(new AnthropicUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function azure(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new AzureOpenAIRequestAdapter(
                $config,
                new OpenAIBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function cerebras(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new CerebrasBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function cohereV1(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new CohereV1RequestAdapter(
                $config,
                new CohereV1BodyFormat($config, new CohereV1MessageFormat())
            ),
            new CohereV1ResponseAdapter(new CohereV1UsageFormat()),
            $httpClient,
            $events
        );
    }

    public function cohereV2(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new CohereV2RequestAdapter(
                $config,
                new CohereV2BodyFormat($config, new OpenAIMessageFormat())
            ),
            new CohereV2ResponseAdapter(new CohereV2UsageFormat()),
            $httpClient,
            $events
        );
    }

    public function deepseek(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat())
            ),
            new DeepseekResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function gemini(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new GeminiRequestAdapter(
                $config,
                new GeminiBodyFormat($config, new GeminiMessageFormat())
            ),
            new GeminiResponseAdapter(new GeminiUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function geminiOAI(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new GeminiOAIBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new GeminiOAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function groq(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new GroqUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function mistral(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new MistralBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function minimaxi(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new MinimaxiBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function openAI(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAIBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function openAICompatible(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function perplexity(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new PerplexityBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function sambaNova(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new SambaNovaBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function xAi(LLMConfig $config, CanHandleHttp $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new XAiMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }
}