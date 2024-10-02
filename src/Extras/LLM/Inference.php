<?php
namespace Cognesy\Instructor\Extras\LLM;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\InferenceRequested;
use Cognesy\Instructor\Extras\Debug\Debug;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\HttpClient;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\CachedContext;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Extras\LLM\Drivers\AnthropicDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\CohereV1Driver;
use Cognesy\Instructor\Extras\LLM\Drivers\CohereV2Driver;
use Cognesy\Instructor\Extras\LLM\Drivers\GeminiDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\MistralDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAICompatibleDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAIDriver;
use Cognesy\Instructor\Extras\LLM\Enums\LLMProviderType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class Inference
{
    protected LLMConfig $config;

    protected CanHandleInference $driver;
    protected CanHandleHttp $httpClient;
    protected EventDispatcher $events;
    protected CachedContext $cachedContext;

    public function __construct(
        string $connection = '',
        LLMConfig $config = null,
        CanHandleHttp $httpClient = null,
        CanHandleInference $driver = null,
        EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();

        $this->httpClient = $httpClient ?? HttpClient::make();
        $this->config = $config ?? LLMConfig::load(connection: $connection
            ?: Settings::get('llm', "defaultConnection")
        );
        $this->driver = $driver ?? $this->makeDriver($this->config, $this->httpClient);
    }

    // STATIC //////////////////////////////////////////////////////////////////

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

    public function withConfig(LLMConfig $config): self {
        $this->config = $config;
        $this->driver = $this->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    public function withConnection(string $connection): self {
        if (empty($connection)) {
            return $this;
        }
        $this->config = LLMConfig::load($connection);
        $this->driver = $this->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    public function withHttpClient(CanHandleHttp $httpClient): self {
        $this->httpClient = $httpClient;
        $this->driver = $this->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    public function withDriver(CanHandleInference $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function withDebug(bool $debug = true) : self {
        Debug::setEnabled($debug); // TODO: fix me - debug should not be global, should be request specific
        return $this;
    }

    public function withCachedContext(
        string|array $messages = [],
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
    ): self {
        $this->cachedContext = new CachedContext($messages, $tools, $toolChoice, $responseFormat);
        return $this;
    }

    public function create(
        string|array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text
    ): InferenceResponse {
        $request = new InferenceRequest(
            $messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode, $this->cachedContext ?? null
        );
        $this->events->dispatch(new InferenceRequested($request));
        return new InferenceResponse(
            response: $this->driver->handle($request),
            driver: $this->driver,
            config: $this->config,
            isStreamed: $options['stream'] ?? false,
            events: $this->events,
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    protected function makeDriver(LLMConfig $config, CanHandleHttp $httpClient): CanHandleInference {
        return match ($config->providerType) {
            LLMProviderType::Anthropic => new AnthropicDriver($config, $httpClient),
            LLMProviderType::Azure => new AzureOpenAIDriver($config, $httpClient),
            LLMProviderType::CohereV1 => new CohereV1Driver($config, $httpClient),
            LLMProviderType::CohereV2 => new CohereV2Driver($config, $httpClient),
            LLMProviderType::Gemini => new GeminiDriver($config, $httpClient),
            LLMProviderType::Mistral => new MistralDriver($config, $httpClient),
            LLMProviderType::OpenAI => new OpenAIDriver($config, $httpClient),
            LLMProviderType::Fireworks,
            LLMProviderType::Groq,
            LLMProviderType::Ollama,
            LLMProviderType::OpenAICompatible,
            LLMProviderType::OpenRouter,
            LLMProviderType::Together,
            => new OpenAICompatibleDriver($config, $httpClient),
            default => throw new InvalidArgumentException("Client not supported: {$config->providerType->value}"),
        };
    }
}
