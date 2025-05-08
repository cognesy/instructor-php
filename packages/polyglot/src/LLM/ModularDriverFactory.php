<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Azure\AzureOpenAIRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\Cerebras\CerebrasBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1BodyFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1MessageFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1RequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1ResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1UsageFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2BodyFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2RequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Deepseek\DeepseekResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\Fireworks\FireworksBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\GeminiOAI\GeminiOAIBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\GeminiOAI\GeminiOAIUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Groq\GroqUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Minimaxi\MinimaxiBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Mistral\MistralBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\ModularLLMDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Perplexity\PerplexityBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\SambaNova\SambaNovaBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\XAI\XAiMessageFormat;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;

/**
 * Factory class for creating inference driver instances based
 * on the specified configuration and provider type.
 */
class ModularDriverFactory
{
    private static array $drivers = [];

    public static function registerDriver(string $name, string|callable $driver): void
    {
        self::$drivers[$name] = match (true) {
            is_callable($driver) => $driver,
            is_string($driver) => fn($config, $httpClient, $events) => new $driver(
                $config,
                $httpClient,
                $events
            ),
        };
    }

    /**
     * Creates and returns an appropriate driver instance based on the given configuration.
     *
     * @param LLMConfig $config Configuration object specifying the provider type and other necessary settings.
     * @param CanHandleHttpRequest $httpClient An HTTP client instance to handle HTTP requests.
     *
     * @return CanHandleInference A driver instance matching the specified provider type.
     * @throws InvalidArgumentException If the provider type is not supported.
     */
    public function makeDriver(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference
    {
        $type = $config->providerType;
        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if ($driver === null) {
            throw new InvalidArgumentException("Provider type not supported - missing built-in or custom driver: {$type}");
        }
        return $driver($config, $httpClient, $events);
    }

    public function anthropic(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function azure(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function cerebras(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function cohereV1(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function cohereV2(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function deepseek(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function fireworks(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events) : CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new FireworksBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function gemini(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function geminiOAI(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function groq(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function mistral(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function minimaxi(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function openAI(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function openAICompatible(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function perplexity(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function sambaNova(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    public function xAi(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
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

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Returns factory to create LLM driver instance
     * @param string $name
     * @return callable|null
     */
    protected function getBundledDriver(string $name): ?callable
    {
        $drivers = [
            'anthropic' => fn($config, $httpClient, $events) => $this->anthropic($config, $httpClient, $events),
            'azure' => fn($config, $httpClient, $events) => $this->azure($config, $httpClient, $events),
            'cerebras' => fn($config, $httpClient, $events) => $this->cerebras($config, $httpClient, $events),
            'cohere1' => fn($config, $httpClient, $events) => $this->cohereV1($config, $httpClient, $events),
            'cohere2' => fn($config, $httpClient, $events) => $this->cohereV2($config, $httpClient, $events),
            'deepseek' => fn($config, $httpClient, $events) => $this->deepseek($config, $httpClient, $events),
            'fireworks' => fn($config, $httpClient, $events) => $this->fireworks($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => $this->gemini($config, $httpClient, $events),
            'gemini-oai' => fn($config, $httpClient, $events) => $this->geminiOAI($config, $httpClient, $events),
            'groq' => fn($config, $httpClient, $events) => $this->groq($config, $httpClient, $events),
            'minimaxi' => fn($config, $httpClient, $events) => $this->minimaxi($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => $this->mistral($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => $this->openAI($config, $httpClient, $events),
            'perplexity' => fn($config, $httpClient, $events) => $this->perplexity($config, $httpClient, $events),
            'sambanova' => fn($config, $httpClient, $events) => $this->sambaNova($config, $httpClient, $events),
            'xai' => fn($config, $httpClient, $events) => $this->xAi($config, $httpClient, $events),
            // OpenAI compatible driver for generic OAI providers
            'a21' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'moonshot' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'openai-compatible' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'openrouter' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'together' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
        ];
        return $drivers[$name] ?? null;
    }
}