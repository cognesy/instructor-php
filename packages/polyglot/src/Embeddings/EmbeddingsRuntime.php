<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

final class EmbeddingsRuntime implements CanCreateEmbeddings
{
    public function __construct(
        private readonly CanHandleVectorization $driver,
        private readonly EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function create(EmbeddingsRequest $request): PendingEmbeddings {
        return new PendingEmbeddings(
            request: $request,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public static function fromConfig(
        EmbeddingsConfig $config,
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        $events = EventBusResolver::using($events);
        $driver = (new EmbeddingsDriverFactory($events))->makeDriver(
            config: $config,
            httpClient: self::resolveHttpClient($events, $httpClient),
        );
        return new self(driver: $driver, events: $events);
    }

    public static function fromResolver(
        CanResolveEmbeddingsConfig $resolver,
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        $events = EventBusResolver::using($events);
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
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        return self::fromResolver(
            resolver: $provider,
            events: $events,
            httpClient: $httpClient,
        );
    }

    public static function fromDsn(
        string $dsn,
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        return self::fromProvider(
            provider: EmbeddingsProvider::dsn($dsn),
            events: $events,
            httpClient: $httpClient,
        );
    }

    public static function using(
        string $preset,
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        return self::fromProvider(
            provider: EmbeddingsProvider::using($preset),
            events: $events,
            httpClient: $httpClient,
        );
    }

    private static function resolveHttpClient(
        null|CanHandleEvents|EventDispatcherInterface $events,
        ?HttpClient $httpClient,
    ): HttpClient {
        if ($httpClient !== null) {
            return $httpClient;
        }
        return (new HttpClientBuilder(events: $events))->create();
    }
}
