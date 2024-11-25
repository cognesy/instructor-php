<?php
namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\InferenceRequested;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\HttpClient;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\CachedContext;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Drivers\AnthropicDriver;
use Cognesy\Instructor\Features\LLM\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV1Driver;
use Cognesy\Instructor\Features\LLM\Drivers\CohereV2Driver;
use Cognesy\Instructor\Features\LLM\Drivers\GeminiDriver;
use Cognesy\Instructor\Features\LLM\Drivers\GeminiOAIDriver;
use Cognesy\Instructor\Features\LLM\Drivers\GrokDriver;
use Cognesy\Instructor\Features\LLM\Drivers\MistralDriver;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAICompatibleDriver;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAIDriver;
use Cognesy\Instructor\Features\LLM\Enums\LLMProviderType;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

/**
 * Class Inference
 *
 * Handles LLM inference operations including configuration management, HTTP client handling, and event dispatching.
 */
class Inference
{
    protected LLMConfig $config;

    protected EventDispatcher $events;
    protected CanHandleInference $driver;
    protected CanHandleHttp $httpClient;
    protected CachedContext $cachedContext;

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
        $this->driver = $driver ?? $this->makeDriver($this->config, $this->httpClient);
    }

    // STATIC //////////////////////////////////////////////////////////////////

    /**
     * Generates a text response based on the provided messages and configuration.
     *
     * @param string|array $messages The input messages to process.
     * @param string $connection The connection string.
     * @param string $model The model identifier.
     * @param array $options Additional options for the inference.
     *
     * @return string The generated text response.
     */
    public static function text(
        string|array $messages,
        string $connection = '',
        string $model = '',
        array $options = []
    ) : string {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: $messages,
                model: $model,
                options: $options,
                mode: Mode::Text,
            )
            ->toText();
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
        $this->driver = $this->makeDriver($this->config, $this->httpClient);
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
        $this->driver = $this->makeDriver($this->config, $this->httpClient);
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
        $this->driver = $this->makeDriver($this->config, $this->httpClient);
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
     * Sets a cached context with provided messages, tools, tool choices, and response format.
     *
     * @param string|array $messages Messages to be cached in the context.
     * @param array $tools Tools to be included in the cached context.
     * @param string|array $toolChoice Tool choices for the cached context.
     * @param array $responseFormat Format for responses in the cached context.
     *
     * @return self
     */
    public function withCachedContext(
        string|array $messages = [],
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
    ): self {
        $this->cachedContext = new CachedContext($messages, $tools, $toolChoice, $responseFormat);
        return $this;
    }

    /**
     * Creates an inference request and returns the inference response.
     *
     * @param InferenceRequest $request The inference request object.
     *
     * @return InferenceResponse The response from the inference request.
     */
    public function withRequest(InferenceRequest $request): InferenceResponse {
        $this->events->dispatch(new InferenceRequested($request));
        return new InferenceResponse(
            response: $this->driver->handle($request),
            driver: $this->driver,
            config: $this->config,
            isStreamed: $request->options['stream'] ?? false,
            events: $this->events,
        );
    }

    /**
     * Creates an inference request and returns the inference response.
     *
     * @param string|array $messages The input messages for the inference.
     * @param string $model The model to be used for the inference.
     * @param array $tools The tools to be used for the inference.
     * @param string|array $toolChoice The choice of tools for the inference.
     * @param array $responseFormat The format of the response.
     * @param array $options Additional options for the inference.
     * @param Mode $mode The mode of operation for the inference.
     *
     * @return InferenceResponse The response from the inference request.
     */
    public function create(
        string|array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text
    ): InferenceResponse {
        return $this->withRequest(new InferenceRequest(
            messages: $messages,
            model: $model,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $responseFormat,
            options: $options,
            mode: $mode,
            cachedContext: $this->cachedContext ?? null
        ));
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
    protected function makeDriver(LLMConfig $config, CanHandleHttp $httpClient): CanHandleInference {
        return match ($config->providerType) {
            LLMProviderType::Anthropic => new AnthropicDriver($config, $httpClient, $this->events),
            LLMProviderType::Azure => new AzureOpenAIDriver($config, $httpClient, $this->events),
            LLMProviderType::CohereV1 => new CohereV1Driver($config, $httpClient, $this->events),
            LLMProviderType::CohereV2 => new CohereV2Driver($config, $httpClient, $this->events),
            LLMProviderType::Gemini => new GeminiDriver($config, $httpClient, $this->events),
            LLMProviderType::GeminiOAI => new GeminiOAIDriver($config, $httpClient, $this->events),
            LLMProviderType::Grok => new GrokDriver($config, $httpClient, $this->events),
            LLMProviderType::Mistral => new MistralDriver($config, $httpClient, $this->events),
            LLMProviderType::OpenAI => new OpenAIDriver($config, $httpClient, $this->events),
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
