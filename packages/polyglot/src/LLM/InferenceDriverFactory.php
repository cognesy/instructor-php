<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
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
use Cognesy\Polyglot\LLM\Drivers\Minimaxi\MinimaxiDriver;
use Cognesy\Polyglot\LLM\Drivers\Mistral\MistralDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleDriver;
use Cognesy\Polyglot\LLM\Drivers\Perplexity\PerplexityDriver;
use Cognesy\Polyglot\LLM\Drivers\SambaNova\SambaNovaDriver;
use Cognesy\Polyglot\LLM\Drivers\XAI\XAiDriver;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;

/**
 * Factory class for creating inference driver instances based
 * on the specified configuration and provider type.
 */
class InferenceDriverFactory
{
    private static array $drivers = [];

    /**
     * Registers driver under given name
     *
     * @param string $name
     * @param string|callable $driver
     * @return void
     */
    public static function registerDriver(string $name, string|callable $driver) : void {
        self::$drivers[$name] = match(true) {
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
    public function makeDriver(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        $type = $config->providerType;
        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if ($driver === null) {
            throw new InvalidArgumentException("Provider type not supported - missing built-in or custom driver: {$type}");
        }
        return $driver($config, $httpClient, $events);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Returns factory to create LLM driver instance
     * @param string $name
     * @return callable|null
     */
    protected function getBundledDriver(string $name) : ?callable {
        $drivers = [
            // Tailored drivers
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
            'minimaxi' => fn($config, $httpClient, $events) => new MinimaxiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new MistralDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'perplexity' => fn($config, $httpClient, $events) => new PerplexityDriver($config, $httpClient, $events),
            'sambanova' => fn($config, $httpClient, $events) => new SambaNovaDriver($config, $httpClient, $events),
            'xai' => fn($config, $httpClient, $events) => new XAiDriver($config, $httpClient, $events),
            // OpenAI compatible driver for generic OAI providers
            'a21' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'moonshot' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'openai-compatible' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'openrouter' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'together' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
       ];
        return $drivers[$name] ?? null;
    }
}