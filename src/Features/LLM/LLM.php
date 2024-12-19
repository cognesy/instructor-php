<?php

namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\InferenceRequested;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\HttpClient;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Drivers\AnthropicDriver;
use Cognesy\Instructor\Features\LLM\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Features\LLM\Drivers\CerebrasDriver;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV1Driver;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV2Driver;
use Cognesy\Instructor\Features\LLM\Drivers\GeminiDriver;
use Cognesy\Instructor\Features\LLM\Drivers\GeminiOAIDriver;
use Cognesy\Instructor\Features\LLM\Drivers\SambaNovaDriver;
use Cognesy\Instructor\Features\LLM\Drivers\XAiDriver;
use Cognesy\Instructor\Features\LLM\Drivers\MistralDriver;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAICompatibleDriver;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAIDriver;
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
            LLMProviderType::Anthropic => new AnthropicDriver($config, $httpClient, $this->events),
            LLMProviderType::Azure => new AzureOpenAIDriver($config, $httpClient, $this->events),
            LLMProviderType::Cerebras => new CerebrasDriver($config, $httpClient, $this->events),
            LLMProviderType::CohereV1 => new CohereV1Driver($config, $httpClient, $this->events),
            LLMProviderType::CohereV2 => new CohereV2Driver($config, $httpClient, $this->events),
            LLMProviderType::Gemini => new GeminiDriver($config, $httpClient, $this->events),
            LLMProviderType::GeminiOAI => new GeminiOAIDriver($config, $httpClient, $this->events),
            LLMProviderType::Mistral => new MistralDriver($config, $httpClient, $this->events),
            LLMProviderType::OpenAI => new OpenAIDriver($config, $httpClient, $this->events),
            LLMProviderType::SambaNova => new SambaNovaDriver($config, $httpClient, $this->events),
            LLMProviderType::XAi => new XAiDriver($config, $httpClient, $this->events),
            LLMProviderType::A21,
            LLMProviderType::Fireworks,
            LLMProviderType::Groq,
            LLMProviderType::Ollama,
            LLMProviderType::OpenAICompatible,
            LLMProviderType::OpenRouter,
            LLMProviderType::Together => new OpenAICompatibleDriver($config, $httpClient, $this->events),
            default => throw new InvalidArgumentException("Client not supported: {$config->providerType->value}"),
        };
    }
}