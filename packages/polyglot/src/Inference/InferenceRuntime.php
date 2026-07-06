<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Logging\EventLog;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Creation\HttpClientDefaults;
use Cognesy\Http\Contracts\CanManageStreamCache;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanProvideInferenceDrivers;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Polyglot\Inference\Core\SensitiveDataRedactor;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Events\InferenceDriverBuilt;
use InvalidArgumentException;

final class InferenceRuntime implements CanCreateInference
{
    public function __construct(
        private readonly CanProcessInferenceRequest $driver,
        private readonly CanHandleEvents $events,
    ) {}

    #[\Override]
    public function create(InferenceRequest $request): PendingInference {
        return new PendingInference(
            execution: InferenceExecution::fromRequest($request),
            driver: $this->driver,
            eventDispatcher: $this->events,
        );
    }

    public static function fromConfig(
        LLMConfig $config,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
        ?CanProvideInferenceDrivers $drivers = null,
    ): InferenceRuntime {
        $events = self::resolveEvents($events);
        $httpClient = self::resolveHttpClient($events, $httpClient);
        $driver = self::makeDriver(
            config: $config,
            events: $events,
            httpClient: $httpClient,
            streamCacheManager: $streamCacheManager,
            drivers: $drivers,
        );
        return new self(
            driver: $driver,
            events: $events,
        );
    }

    private static function fromResolver(
        CanResolveLLMConfig $resolver,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
        ?CanProvideInferenceDrivers $drivers = null,
    ): InferenceRuntime {
        $events = self::resolveEvents($events);
        $config = $resolver->resolveConfig();
        $httpClient = self::resolveHttpClient($events, $httpClient);
        $driver = match (true) {
            $resolver instanceof HasExplicitInferenceDriver && $resolver->explicitInferenceDriver() !== null
                => self::withStreamCacheManager($resolver->explicitInferenceDriver(), $streamCacheManager),
            default => self::makeDriver(
                config: $config,
                events: $events,
                httpClient: $httpClient,
                streamCacheManager: $streamCacheManager,
                drivers: $drivers,
            ),
        };

        return new self(
            driver: $driver,
            events: $events,
        );
    }

    public static function fromProvider(
        LLMProvider $provider,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
        ?CanProvideInferenceDrivers $drivers = null,
    ): InferenceRuntime {
        return self::fromResolver(
            resolver: $provider,
            events: $events,
            httpClient: $httpClient,
            streamCacheManager: $streamCacheManager,
            drivers: $drivers,
        );
    }

    /** @param callable(object):void $listener */
    public function onEvent(string $class, callable $listener, int $priority = 0): InferenceRuntime {
        $this->events->addListener($class, $listener, $priority);
        return $this;
    }

    /** @param callable(object):void $listener */
    public function wiretap(callable $listener): InferenceRuntime {
        $this->events->wiretap($listener);
        return $this;
    }

    private static function resolveHttpClient(
        CanHandleEvents $events,
        ?CanSendHttpRequests $httpClient,
    ): CanSendHttpRequests {
        if ($httpClient !== null) {
            return $httpClient;
        }
        // Implicit-build path: consult the ambient middleware registry (default
        // empty). Explicit clients returned above never reach this hook.
        return HttpClientDefaults::applyTo(new HttpClientBuilder(events: $events))->create();
    }

    private static function resolveEvents(?CanHandleEvents $events): CanHandleEvents {
        if ($events !== null) {
            return $events;
        }
        return EventLog::root('polyglot.inference.runtime');
    }

    private static function withStreamCacheManager(
        CanProcessInferenceRequest $driver,
        ?CanManageStreamCache $streamCacheManager,
    ): CanProcessInferenceRequest {
        return match (true) {
            $streamCacheManager === null => $driver,
            $driver instanceof BaseInferenceRequestDriver => $driver->withStreamCacheManager($streamCacheManager),
            default => $driver,
        };
    }


    private static function makeDriver(
        LLMConfig $config,
        CanHandleEvents $events,
        CanSendHttpRequests $httpClient,
        ?CanManageStreamCache $streamCacheManager,
        ?CanProvideInferenceDrivers $drivers,
    ): CanProcessInferenceRequest {
        $driverName = $config->driver;
        if (empty($driverName)) {
            throw new InvalidArgumentException('Provider type not specified in the configuration.');
        }

        $driver = self::withStreamCacheManager(
            driver: self::resolveDrivers($drivers)->makeDriver($driverName, $config, $httpClient, $events),
            streamCacheManager: $streamCacheManager,
        );

        $events->dispatch(new InferenceDriverBuilt([
            'driverClass' => get_class($driver),
            'config' => self::redactedConfig($config),
            'httpClient' => get_class($httpClient),
        ]));

        return $driver;
    }

    private static function resolveDrivers(?CanProvideInferenceDrivers $drivers): CanProvideInferenceDrivers {
        return $drivers ?? BundledInferenceDrivers::registry();
    }

    /**
     * @return array<string,mixed>
     */
    private static function redactedConfig(LLMConfig $config): array {
        return SensitiveDataRedactor::redactValues($config->toArray());
    }
}
