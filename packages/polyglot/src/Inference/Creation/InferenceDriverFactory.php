<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanManageStreamCache;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\A21\A21Driver;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\Inference\Drivers\Azure\AzureDriver;
use Cognesy\Polyglot\Inference\Drivers\Bedrock\BedrockOpenAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Cerebras\CerebrasDriver;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2Driver;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekDriver;
use Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksDriver;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqDriver;
use Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceDriver;
use Cognesy\Polyglot\Inference\Drivers\Inception\InceptionDriver;
use Cognesy\Polyglot\Inference\Drivers\Meta\MetaDriver;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiDriver;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAIResponses\OpenAIResponsesDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenRouter\OpenRouterDriver;
use Cognesy\Polyglot\Inference\Drivers\Perplexity\PerplexityDriver;
use Cognesy\Polyglot\Inference\Drivers\SambaNova\SambaNovaDriver;
use Cognesy\Polyglot\Inference\Drivers\XAI\XAiDriver;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Events\InferenceDriverBuilt;
use InvalidArgumentException;

/**
 * Factory class for creating inference driver instances based
 * on the specified configuration and provider type.
 */
class InferenceDriverFactory
{
    /** @var array<string, callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest> */
    private static array $registeredDrivers = [];

    /** @var array<string, callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest> */
    private array $drivers = [];

    private array $bundledDrivers;
    private CanHandleEvents $events;

    public function __construct(
        CanHandleEvents $events,
    ) {
        $this->events = $events;
        $this->bundledDrivers = $this->bundledDrivers();
        $this->drivers = self::$registeredDrivers;
    }

    /**
     * Registers driver under given name
     * @param string|callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest $driver
     */
    public static function registerDriver(string $name, string|callable $driver) : void {
        self::$registeredDrivers[$name] = self::toDriverFactory($driver);
    }

    public static function unregisterDriver(string $name): void {
        unset(self::$registeredDrivers[$name]);
    }

    public static function resetDrivers(): void {
        self::$registeredDrivers = [];
    }

    public static function hasDriver(string $name): bool {
        return isset(self::$registeredDrivers[$name]);
    }

    /** @return array<string> */
    public static function registeredDrivers(): array {
        return array_keys(self::$registeredDrivers);
    }

    /**
     * Registers driver in this factory instance.
     * @param string|callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest $driver
     */
    public function withDriver(string $name, string|callable $driver): self {
        $copy = clone $this;
        $copy->drivers[$name] = self::toDriverFactory($driver);
        return $copy;
    }

    public function withoutDriver(string $name): self {
        $copy = clone $this;
        unset($copy->drivers[$name]);
        return $copy;
    }

    public function hasLocalDriver(string $name): bool {
        return isset($this->drivers[$name]);
    }

    /** @return array<string> */
    public function localDrivers(): array {
        return array_keys($this->drivers);
    }

    /**
     * Creates and returns an appropriate driver instance based on the given configuration.
     */
    public function makeDriver(
        LLMConfig $config,
        HttpClient $httpClient,
        ?CanManageStreamCache $streamCacheManager = null,
    ): CanProcessInferenceRequest {
        $driver = $config->driver;
        if (empty($driver)) {
            throw new InvalidArgumentException("Provider type not specified in the configuration.");
        }

        $driverFactory = $this->drivers[$driver] ?? $this->getBundledDriver($driver);
        if ($driverFactory === null) {
            throw new InvalidArgumentException("Provider type not supported - missing built-in or custom driver: {$driver}");
        }

        $driver = $driverFactory($config, $httpClient, $this->events);
        $driver = match (true) {
            $streamCacheManager === null => $driver,
            $driver instanceof BaseInferenceRequestDriver => $driver->withStreamCacheManager($streamCacheManager),
            default => $driver,
        };

        $this->events->dispatch(new InferenceDriverBuilt([
            'driverClass' => get_class($driver),
            'config' => $this->redactedConfig($config),
            'httpClient' => get_class($httpClient),
        ]));

        return $driver;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Returns factory to create LLM driver instance
     * @return (callable(LLMConfig, HttpClient, CanHandleEvents): CanProcessInferenceRequest)|null
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
            'bedrock-openai' => fn($config, $httpClient, $events) => new BedrockOpenAIDriver($config, $httpClient, $events),
            'cerebras' => fn($config, $httpClient, $events) => new CerebrasDriver($config, $httpClient, $events),
            'cohere' => fn($config, $httpClient, $events) => new CohereV2Driver($config, $httpClient, $events),
            'deepseek' => fn($config, $httpClient, $events) => new DeepseekDriver($config, $httpClient, $events),
            'fireworks' => fn($config, $httpClient, $events) => new FireworksDriver($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => new GeminiDriver($config, $httpClient, $events),
            'gemini-oai' => fn($config, $httpClient, $events) => new GeminiOAIDriver($config, $httpClient, $events),
            'groq' => fn($config, $httpClient, $events) => new GroqDriver($config, $httpClient, $events),
            'huggingface' => fn($config, $httpClient, $events) => new HuggingFaceDriver($config, $httpClient, $events),
            'inception' => fn($config, $httpClient, $events) => new InceptionDriver($config, $httpClient, $events),
            'meta' => fn($config, $httpClient, $events) => new MetaDriver($config, $httpClient, $events),
            'minimaxi' => fn($config, $httpClient, $events) => new MinimaxiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new MistralDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'openai-responses' => fn($config, $httpClient, $events) => new OpenAIResponsesDriver($config, $httpClient, $events),
            'openresponses' => fn($config, $httpClient, $events) => new OpenResponsesDriver($config, $httpClient, $events),
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

    /**
     * @param string|callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest $driver
     * @return callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest
     */
    private static function toDriverFactory(string|callable $driver): callable {
        return match(true) {
            is_callable($driver) => static function (LLMConfig $config, HttpClient $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver factory must return ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
            is_string($driver) => static function (LLMConfig $config, HttpClient $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = new $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver class must implement ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function redactedConfig(LLMConfig $config): array {
        return $this->redactSensitiveValues($config->toArray());
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactSensitiveValues(array $data): array {
        $redacted = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            if (!is_array($value)) {
                $redacted[$key] = $value;
                continue;
            }

            $redacted[$key] = $this->redactSensitiveValues($value);
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        if (in_array($normalized, ['apikey', 'authorization', 'proxyauthorization', 'token', 'accesstoken', 'refreshtoken', 'secret', 'password', 'cookie', 'setcookie'], true)) {
            return true;
        }

        if (str_contains($normalized, 'apikey')) {
            return true;
        }

        if (str_contains($normalized, 'authorization')) {
            return true;
        }

        if (str_contains($normalized, 'cookie')) {
            return true;
        }

        return str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'password');
    }
}
