<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Contracts\CanManageStreamCache;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanProvideInferenceDrivers;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
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
        private readonly ?Pricing $pricing = null,
    ) {}

    #[\Override]
    public function create(InferenceRequest $request): PendingInference {
        return new PendingInference(
            execution: InferenceExecution::fromRequest($request),
            driver: $this->driver,
            eventDispatcher: $this->events,
            pricing: $this->pricing,
        );
    }

    public static function fromConfig(
        LLMConfig $config,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
        ?CanProvideInferenceDrivers $drivers = null,
    ): self {
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
            pricing: self::toOptionalPricing($config->getPricing()),
        );
    }

    private static function fromResolver(
        CanResolveLLMConfig $resolver,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
        ?CanProvideInferenceDrivers $drivers = null,
    ): self {
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
            pricing: self::toOptionalPricing($config->getPricing()),
        );
    }

    public static function fromProvider(
        LLMProvider $provider,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
        ?CanProvideInferenceDrivers $drivers = null,
    ): self {
        return self::fromResolver(
            resolver: $provider,
            events: $events,
            httpClient: $httpClient,
            streamCacheManager: $streamCacheManager,
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
        return new EventDispatcher(name: 'polyglot.inference.runtime');
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

    private static function toOptionalPricing(Pricing $pricing): ?Pricing {
        return match (true) {
            $pricing->hasAnyPricing() => $pricing,
            default => null,
        };
    }

    private static function makeDriver(
        LLMConfig $config,
        CanHandleEvents $events,
        HttpClient $httpClient,
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
        return self::redactSensitiveValues($config->toArray());
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function redactSensitiveValues(array $data): array {
        $redacted = [];

        foreach ($data as $key => $value) {
            $redacted[$key] = match (true) {
                self::isSensitiveKey((string) $key) => '[REDACTED]',
                is_array($value) => self::redactSensitiveValues($value),
                default => $value,
            };
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        return match (true) {
            in_array($normalized, ['apikey', 'authorization', 'proxyauthorization', 'token', 'accesstoken', 'refreshtoken', 'secret', 'password', 'cookie', 'setcookie'], true) => true,
            str_contains($normalized, 'apikey') => true,
            str_contains($normalized, 'authorization') => true,
            str_contains($normalized, 'cookie') => true,
            default => str_contains($normalized, 'token')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'password'),
        };
    }
}
