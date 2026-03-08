<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;

final class EmbeddingsRuntime implements CanCreateEmbeddings
{
    public function __construct(
        private readonly CanHandleVectorization $driver,
        private readonly CanHandleEvents $events,
    ) {}

    #[\Override]
    public function create(EmbeddingsRequest $request): PendingEmbeddings {
        if (!$request->hasInputs()) {
            throw new \InvalidArgumentException('Input data is required');
        }

        return new PendingEmbeddings(
            request: $request,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public static function fromConfig(
        EmbeddingsConfig $config,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        $events = self::resolveEvents($events);
        $driver = (new EmbeddingsDriverFactory($events))->makeDriver(
            config: $config,
            httpClient: self::resolveHttpClient($events, $httpClient),
        );
        return new self(driver: $driver, events: $events);
    }

    private static function fromResolver(
        CanResolveEmbeddingsConfig $resolver,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        $events = self::resolveEvents($events);
        $config = $resolver->resolveConfig();
        $driver = match (true) {
            $resolver instanceof HasExplicitEmbeddingsDriver && $resolver->explicitEmbeddingsDriver() !== null
                => $resolver->explicitEmbeddingsDriver(),
            default => (new EmbeddingsDriverFactory($events))->makeDriver(
                config: $config,
                httpClient: self::resolveHttpClient($events, $httpClient),
            ),
        };

        assert($driver instanceof CanHandleVectorization);
        return new self(driver: $driver, events: $events);
    }

    public static function fromProvider(
        EmbeddingsProvider $provider,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        return self::fromResolver(
            resolver: $provider,
            events: $events,
            httpClient: $httpClient,
        );
    }

    public function onEvent(string $class, callable $listener, int $priority = 0): self {
        $this->events->addListener($class, $listener, $priority);
        return $this;
    }

    public function wiretap(callable $listener): self {
        $this->events->wiretap($listener);
        return $this;
    }

    private static function resolveHttpClient(
        CanHandleEvents $events,
        ?HttpClient $httpClient,
    ): HttpClient {
        if ($httpClient !== null) {
            return $httpClient;
        }
        return (new HttpClientBuilder(events: $events))->create();
    }

    private static function resolveEvents(?CanHandleEvents $events): CanHandleEvents {
        if ($events !== null) {
            return $events;
        }
        return new EventDispatcher(name: 'polyglot.embeddings.runtime');
    }
}
