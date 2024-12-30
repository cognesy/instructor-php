<?php

namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\InferenceRequested;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\HttpClient;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Drivers\Anthropic\AnthropicRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\Azure\AzureOpenAIRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\Cerebras\CerebrasRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV1\CohereV1RequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV1\CohereV1ResponseAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV2\CohereV2RequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\Gemini\GeminiRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\GeminiOAI\GeminiOAIRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\DefaultDriver;
use Cognesy\Instructor\Features\LLM\Drivers\Mistral\MistralRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAICompatible\OpenAICompatibleRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\SambaNova\SambaNovaRequestAdapter;
use Cognesy\Instructor\Features\LLM\Drivers\XAI\XAiRequestAdapter;
use Cognesy\Instructor\Features\LLM\Enums\LLMProviderType;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

/**
 * Class LLM
 *
 * This class represents a Language Learning Model interface, handling
 * configurations, HTTP client integrations, inference drivers, and event dispatching.
 */
class LLM
{
    protected LLMConfig $config;

    protected EventDispatcher $events;
    protected CanHandleHttp $httpClient;
    protected CanHandleInference $driver;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param string $connection The connection string.
     * @param LLMConfig|null $config Configuration object.
     * @param CanHandleHttp|null $httpClient HTTP client handler.
     * @param CanHandleInference|null $driver Inference handler.
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        string $connection = '',
        LLMConfig $config = null,
        CanHandleHttp $httpClient = null,
        CanHandleInference $driver = null,
        EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->config = $config ?? LLMConfig::load(
            connection: $connection ?: Settings::get('llm', "defaultConnection")
        );
        $this->httpClient = $httpClient ?? HttpClient::make(client: $this->config->httpClient, events: $this->events);
        $this->driver = $driver ?? $this->makeInferenceDriver($this->config, $this->httpClient);
    }

    // STATIC //////////////////////////////////////////////////////////////////

    /**
     * Creates a new LLM instance for the specified connection
     *
     * @param string $connection
     * @return self
     */
    public static function connection(string $connection = ''): self {
        return new self(connection: $connection);
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function withConfig(LLMConfig $config): self {
        $this->config = $config;
        $this->driver = $this->makeInferenceDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Sets the connection and updates the configuration and driver.
     *
     * @param string $connection The connection string to be used.
     *
     * @return self Returns the current instance with the updated connection.
     */
    public function withConnection(string $connection): self {
        if (empty($connection)) {
            return $this;
        }
        $this->config = LLMConfig::load($connection);
        $this->driver = $this->makeInferenceDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Sets a custom HTTP client and updates the inference driver accordingly.
     *
     * @param CanHandleHttp $httpClient The custom HTTP client handler.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function withHttpClient(CanHandleHttp $httpClient): self {
        $this->httpClient = $httpClient;
        $this->driver = $this->makeInferenceDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Sets the driver for inference handling and returns the current instance.
     *
     * @param CanHandleInference $driver The inference handler to be set.
     *
     * @return self
     */
    public function withDriver(CanHandleInference $driver): self {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true) : self {
        Debug::setEnabled($debug); // TODO: fix me - debug should not be global, should be request specific
        return $this;
    }

    /**
     * Returns the current configuration object.
     *
     * @return LLMConfig
     */
    public function config() : LLMConfig {
        return $this->config;
    }

    /**
     * Returns the current inference driver.
     *
     * @return CanHandleInference
     */
    public function driver() : CanHandleInference {
        return $this->driver;
    }

    /**
     * Returns the HTTP response object for given inference request
     *
     * @param InferenceRequest $request
     * @return CanAccessResponse
     */
    public function handleInferenceRequest(InferenceRequest $request) : CanAccessResponse {
        $this->events->dispatch(new InferenceRequested($request));
        return $this->driver->handle($request);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Creates and returns an appropriate driver instance based on the given configuration.
     *
     * @param LLMConfig $config Configuration object specifying the provider type and other necessary settings.
     * @param CanHandleHttp $httpClient An HTTP client instance to handle HTTP requests.
     *
     * @return CanHandleInference A driver instance matching the specified provider type.
     * @throws InvalidArgumentException If the provider type is not supported.
     */
    protected function makeInferenceDriver(LLMConfig $config, CanHandleHttp $httpClient): CanHandleInference {
        return match ($config->providerType) {
            // Tailored drivers
            LLMProviderType::Anthropic => new DefaultDriver($config, new AnthropicRequestAdapter($config), new AnthropicResponseAdapter, $httpClient, $this->events),
            LLMProviderType::Azure => new DefaultDriver($config, new AzureOpenAIRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            LLMProviderType::Cerebras => new DefaultDriver($config, new CerebrasRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            LLMProviderType::CohereV1 => new DefaultDriver($config, new CohereV1RequestAdapter($config), new CohereV1ResponseAdapter, $httpClient, $this->events),
            LLMProviderType::CohereV2 => new DefaultDriver($config, new CohereV2RequestAdapter($config), new CohereV2ResponseAdapter, $httpClient, $this->events),
            LLMProviderType::Gemini => new DefaultDriver($config, new GeminiRequestAdapter($config), new GeminiResponseAdapter, $httpClient, $this->events),
            LLMProviderType::GeminiOAI => new DefaultDriver($config, new GeminiOAIRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            LLMProviderType::Mistral => new DefaultDriver($config, new MistralRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            LLMProviderType::OpenAI => new DefaultDriver($config, new OpenAIRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            LLMProviderType::SambaNova => new DefaultDriver($config, new SambaNovaRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            LLMProviderType::XAi => new DefaultDriver($config, new XAiRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            // OpenAI compatible driver for generic OAI providers
            LLMProviderType::A21,
            LLMProviderType::DeepSeek,
            LLMProviderType::Fireworks,
            LLMProviderType::Groq,
            LLMProviderType::Ollama,
            LLMProviderType::OpenAICompatible,
            LLMProviderType::OpenRouter,
            LLMProviderType::Together => new DefaultDriver($config, new OpenAICompatibleRequestAdapter($config), new OpenAIResponseAdapter, $httpClient, $this->events),
            default => throw new InvalidArgumentException("Client not supported: {$config->providerType->value}"),
        };
    }
}