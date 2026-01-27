<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Drivers\A21\A21Driver;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\Inference\Drivers\Azure\AzureDriver;
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
use Cognesy\Polyglot\Inference\Events\InferenceDriverBuilt;
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
    private CanHandleEvents $events;

    public function __construct(
        CanHandleEvents|EventDispatcherInterface $events,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->bundledDrivers = $this->bundledDrivers();
    }

    /**
     * Registers driver under given name
     * @param string|callable(\Cognesy\Polyglot\Inference\Config\LLMConfig, \Cognesy\Http\HttpClient, \Psr\EventDispatcher\EventDispatcherInterface): \Cognesy\Polyglot\Inference\Contracts\CanHandleInference $driver
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
    public function makeDriver(LLMConfig $config, HttpClient $httpClient): CanHandleInference {
        $driver = $config->driver;
        if (empty($driver)) {
            throw new InvalidArgumentException("Provider type not specified in the configuration.");
        }

        $driverFactory = self::$drivers[$driver] ?? $this->getBundledDriver($driver);
        if ($driverFactory === null) {
            throw new InvalidArgumentException("Provider type not supported - missing built-in or custom driver: {$driver}");
        }

        $driver = $driverFactory($config, $httpClient, $this->events);

        $this->events->dispatch(new InferenceDriverBuilt([
            'driverClass' => get_class($driver),
            'config' => $config->toArray(),
            'httpClient' => get_class($httpClient),
        ]));

        return $driver;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Returns factory to create LLM driver instance
     * @return (callable(LLMConfig, HttpClient, EventDispatcherInterface): CanHandleInference)|null
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
}
