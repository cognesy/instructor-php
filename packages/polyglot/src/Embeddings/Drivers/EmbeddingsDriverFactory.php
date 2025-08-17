<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Drivers\Azure\AzureOpenAIDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Embeddings\Drivers\Jina\JinaDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsDriverBuilt;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class EmbeddingsDriverFactory
{
    protected static array $drivers = [];

    protected EventDispatcherInterface $events;

    public function __construct(
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
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
     * @param \Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig $config
     * @param HttpClient $httpClient
     * @return CanHandleVectorization
     */
    public function makeDriver(EmbeddingsConfig $config, HttpClient $httpClient) : CanHandleVectorization {
        $type = $config->driver ?? 'openai';

        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if (!$driver) {
            throw new InvalidArgumentException("Unknown driver: {$type}");
        }

        $this->events->dispatch(new EmbeddingsDriverBuilt([
            'driver' => get_class($driver),
            'config' => $config->toArray(),
            'httpClient' => $httpClient ? get_class($httpClient) : null,
        ]));

        return $driver($config, $httpClient, $this->events);
    }

    // INTERNAL ////////////////////////////////////////////////////

    protected function getBundledDriver(string $type) : ?callable {
        return match ($type) {
            'azure' => fn($config, $httpClient, $events) => new AzureOpenAIDriver($config, $httpClient, $events),
            'cohere' => fn($config, $httpClient, $events) => new CohereDriver($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => new GeminiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'jina' => fn($config, $httpClient, $events) => new JinaDriver($config, $httpClient, $events),
            default => null,
        };
    }
}