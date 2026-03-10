<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsDrivers;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Creation\BundledEmbeddingsDrivers;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsDriverBuilt;

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
        ?CanSendHttpRequests $httpClient = null,
        ?CanProvideEmbeddingsDrivers $drivers = null,
    ): self {
        $events = self::resolveEvents($events);
        $httpClient = self::resolveHttpClient($events, $httpClient);
        $driver = self::makeDriver(
            config: $config,
            events: $events,
            httpClient: $httpClient,
            drivers: $drivers,
        );
        return new self(driver: $driver, events: $events);
    }

    private static function fromResolver(
        CanResolveEmbeddingsConfig $resolver,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?CanProvideEmbeddingsDrivers $drivers = null,
    ): self {
        $events = self::resolveEvents($events);
        $config = $resolver->resolveConfig();
        $driver = match (true) {
            $resolver instanceof HasExplicitEmbeddingsDriver && $resolver->explicitEmbeddingsDriver() !== null
                => $resolver->explicitEmbeddingsDriver(),
            default => self::makeDriver(
                config: $config,
                events: $events,
                httpClient: self::resolveHttpClient($events, $httpClient),
                drivers: $drivers,
            ),
        };

        return new self(driver: $driver, events: $events);
    }

    public static function fromProvider(
        EmbeddingsProvider $provider,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?CanProvideEmbeddingsDrivers $drivers = null,
    ): self {
        return self::fromResolver(
            resolver: $provider,
            events: $events,
            httpClient: $httpClient,
            drivers: $drivers,
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

    private static function makeDriver(
        EmbeddingsConfig $config,
        CanHandleEvents $events,
        CanSendHttpRequests $httpClient,
        ?CanProvideEmbeddingsDrivers $drivers,
    ): CanHandleVectorization {
        $driverName = $config->driver ?? 'openai';
        if (empty($driverName)) {
            throw new \InvalidArgumentException('Provider type not specified in the configuration.');
        }

        $driver = self::resolveDrivers($drivers)->makeDriver($driverName, $config, $httpClient, $events);

        $events->dispatch(new EmbeddingsDriverBuilt([
            'driverClass' => get_class($driver),
            'config' => $config->toArray(),
            'httpClient' => get_class($httpClient),
        ]));

        return $driver;
    }

    private static function resolveDrivers(?CanProvideEmbeddingsDrivers $drivers): CanProvideEmbeddingsDrivers {
        return $drivers ?? BundledEmbeddingsDrivers::registry();
    }

    private static function resolveHttpClient(
        CanHandleEvents $events,
        ?CanSendHttpRequests $httpClient,
    ): CanSendHttpRequests {
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
