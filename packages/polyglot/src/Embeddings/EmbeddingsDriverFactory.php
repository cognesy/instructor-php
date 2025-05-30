<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Drivers\AzureOpenAIDriver;
use Cognesy\Polyglot\Embeddings\Drivers\CohereDriver;
use Cognesy\Polyglot\Embeddings\Drivers\GeminiDriver;
use Cognesy\Polyglot\Embeddings\Drivers\JinaDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAIDriver;
use Cognesy\Utils\Events\Contracts\EventListenerInterface;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class EmbeddingsDriverFactory
{
    protected static array $drivers = [];

    protected EventDispatcherInterface $events;

    public function __construct(
        EventDispatcherInterface $events,
        EventListenerInterface $listener,
    ) {
        $this->events = $events;
        $this->listener = $listener;
    }

    public static function registerDriver(string $name, string|callable $driver) {
        self::$drivers[$name] = match(true) {
            is_string($driver) => fn($config, $httpClient, $events) => new $driver($config, $httpClient, $events),
            is_callable($driver) => $driver,
        };
    }

    /**
     * Returns the driver for the specified configuration.
     *
     * @param EmbeddingsConfig $config
     * @param HttpClient $httpClient
     * @return CanHandleVectorization
     */
    public function makeDriver(EmbeddingsConfig $config, HttpClient $httpClient) : CanHandleVectorization {
        $type = $config->providerType ?? 'openai';
        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if (!$driver) {
            throw new InvalidArgumentException("Unknown driver: {$type}");
        }
        return $driver($config, $httpClient, $this->events);
    }

    // INTERNAL ////////////////////////////////////////////////////

    protected function getBundledDriver(string $type) : ?callable {
        return match ($type) {
            'azure' => fn($config, $httpClient, $events) => new AzureOpenAIDriver($config, $httpClient, $events),
            'cohere1' => fn($config, $httpClient, $events) => new CohereDriver($config, $httpClient, $events),
            'cohere2' => fn($config, $httpClient, $events) => new CohereDriver($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => new GeminiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'jina' => fn($config, $httpClient, $events) => new JinaDriver($config, $httpClient, $events),
            default => null,
        };
    }
}