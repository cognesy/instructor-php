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
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;

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
    ): self {
        $events = self::resolveEvents($events);
        $driver = (new InferenceDriverFactory($events))->makeDriver(
            config: $config,
            httpClient: self::resolveHttpClient($events, $httpClient),
            streamCacheManager: $streamCacheManager,
        );
        return new self(
            driver: $driver,
            events: $events,
            pricing: self::toOptionalPricing($config->getPricing()),
        );
    }

    public static function fromResolver(
        CanResolveLLMConfig $resolver,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
    ): self {
        $events = self::resolveEvents($events);
        $config = $resolver->resolveConfig();
        $driver = match (true) {
            $resolver instanceof HasExplicitInferenceDriver && $resolver->explicitInferenceDriver() !== null
                => self::withStreamCacheManager($resolver->explicitInferenceDriver(), $streamCacheManager),
            default => (new InferenceDriverFactory($events))->makeDriver(
                config: $config,
                httpClient: self::resolveHttpClient($events, $httpClient),
                streamCacheManager: $streamCacheManager,
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
    ): self {
        return self::fromResolver(
            resolver: $provider,
            events: $events,
            httpClient: $httpClient,
            streamCacheManager: $streamCacheManager,
        );
    }

    public static function fromDsn(
        string $dsn,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
    ): self {
        return self::fromProvider(
            provider: LLMProvider::dsn($dsn),
            events: $events,
            httpClient: $httpClient,
            streamCacheManager: $streamCacheManager,
        );
    }

    public static function using(
        string $preset,
        ?CanHandleEvents $events = null,
        ?HttpClient $httpClient = null,
        ?CanManageStreamCache $streamCacheManager = null,
    ): self {
        return self::fromProvider(
            provider: LLMProvider::using($preset),
            events: $events,
            httpClient: $httpClient,
            streamCacheManager: $streamCacheManager,
        );
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
}
