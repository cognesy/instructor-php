<?php

namespace Cognesy\Polyglot\LLM\Drivers;

use Cognesy\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\LLM\Config\LLMConfig;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Drivers\A21\A21Driver;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\LLM\Drivers\Azure\AzureDriver;
use Cognesy\Polyglot\LLM\Drivers\Cerebras\CerebrasDriver;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1Driver;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2Driver;
use Cognesy\Polyglot\LLM\Drivers\Deepseek\DeepseekDriver;
use Cognesy\Polyglot\LLM\Drivers\Fireworks\FireworksDriver;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\LLM\Drivers\GeminiOAI\GeminiOAIDriver;
use Cognesy\Polyglot\LLM\Drivers\Groq\GroqDriver;
use Cognesy\Polyglot\LLM\Drivers\HuggingFace\HuggingFaceDriver;
use Cognesy\Polyglot\LLM\Drivers\Meta\MetaDriver;
use Cognesy\Polyglot\LLM\Drivers\Minimaxi\MinimaxiDriver;
use Cognesy\Polyglot\LLM\Drivers\Mistral\MistralDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenRouter\OpenRouterDriver;
use Cognesy\Polyglot\LLM\Drivers\Perplexity\PerplexityDriver;
use Cognesy\Polyglot\LLM\Drivers\SambaNova\SambaNovaDriver;
use Cognesy\Polyglot\LLM\Drivers\XAI\XAiDriver;
use Cognesy\Polyglot\LLM\Events\InferenceDriverBuilt;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Factory class for creating inference driver instances based
 * on the specified configuration and provider type.
 */
class InferenceDriverFactory
{
    private static array $drivers = [];
    private array $bundledDrivers;

    public function __construct(
        protected EventDispatcherInterface  $events,
        protected CanRegisterEventListeners $listener,
    ) {
        $this->bundledDrivers = $this->bundledDrivers();
    }

    /**
     * Registers driver under given name
     */
    public static function registerDriver(string $name, string|callable $driver) : void {
        self::$drivers[$name] = match(true) {
            is_callable($driver) => $driver,
            is_string($driver) => fn($config, $httpClient, $events) => new $driver($config, $httpClient, $events),
        };
    }

    /**
     * Creates and returns an appropriate driver instance based on the given configuration.
     */
    public function makeDriver(LLMConfig $config, ?HttpClient $httpClient = null): CanHandleInference {
        $driver = $config->driver;
        if (empty($driver)) {
            throw new InvalidArgumentException("Provider type not specified in the configuration.");
        }

        $driverFactory = self::$drivers[$driver] ?? $this->getBundledDriver($driver);
        if ($driverFactory === null) {
            throw new InvalidArgumentException("Provider type not supported - missing built-in or custom driver: {$driver}");
        }

        $httpClient = match(true) {
            !is_null($httpClient) => $httpClient,
            !empty($config->httpClientPreset) => (new HttpClientBuilder($this->events, $this->listener))->withPreset($config->httpClientPreset)->create(),
            default => (new HttpClientBuilder($this->events, $this->listener))->create(),
        };

        $driver = $driverFactory(
            config: $config,
            httpClient: $httpClient,
            events: $this->events
        );

        $this->events->dispatch(new InferenceDriverBuilt(
            driverClass: get_class($driver),
            config: $config,
            httpClientInfo: $httpClient->toDebugArray(),
        ));

        return $driver;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Returns factory to create LLM driver instance
     */
    protected function getBundledDriver(string $name) : ?callable {
       return $this->bundledDrivers[$name] ?? null;
    }

    protected function bundledDrivers() : array {
        return [
            // Tailored drivers
            'a21' => fn($config, $httpClient, $events) => new A21Driver($config, $httpClient, $events),
            'anthropic' => fn($config, $httpClient, $events) => new AnthropicDriver($config, $httpClient, $events),
            'azure' => fn($config, $httpClient, $events) => new AzureDriver($config, $httpClient, $events),
            'cerebras' => fn($config, $httpClient, $events) => new CerebrasDriver($config, $httpClient, $events),
            'cohere1' => fn($config, $httpClient, $events) => new CohereV1Driver($config, $httpClient, $events),
            'cohere2' => fn($config, $httpClient, $events) => new CohereV2Driver($config, $httpClient, $events),
            'deepseek' => fn($config, $httpClient, $events) => new DeepseekDriver($config, $httpClient, $events),
            'fireworks' => fn($config, $httpClient, $events) => new FireworksDriver($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => new GeminiDriver($config, $httpClient, $events),
            'gemini-oai' => fn($config, $httpClient, $events) => new GeminiOAIDriver($config, $httpClient, $events),
            'groq' => fn($config, $httpClient, $events) => new GroqDriver($config, $httpClient, $events),
            'huggingface' => fn($config, $httpClient, $events) => new HuggingFaceDriver($config, $httpClient, $events),
            'meta' => fn($config, $httpClient, $events) => new MetaDriver($config, $httpClient, $events),
            'minimaxi' => fn($config, $httpClient, $events) => new MinimaxiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new MistralDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'openrouter' => fn($config, $httpClient, $events) => new OpenRouterDriver($config, $httpClient, $events),
            'perplexity' => fn($config, $httpClient, $events) => new PerplexityDriver($config, $httpClient, $events),
            'sambanova' => fn($config, $httpClient, $events) => new SambaNovaDriver($config, $httpClient, $events),
            'xai' => fn($config, $httpClient, $events) => new XAiDriver($config, $httpClient, $events),
            // OpenAI compatible driver for generic OAI providers
            'moonshot' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'openai-compatible' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'together' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
       ];
    }
}