<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanResolveInferencePricing;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Pricing\StaticPricingResolver;
use Psr\EventDispatcher\EventDispatcherInterface;

final class InferenceRuntime implements CanCreateInference
{
    public function __construct(
        private readonly CanProcessInferenceRequest $driver,
        private readonly EventDispatcherInterface $events,
        private readonly ?CanResolveInferencePricing $pricingResolver = null,
    ) {}

    #[\Override]
    public function create(InferenceRequest $request): PendingInference {
        $pricing = $this->pricingResolver?->resolvePricing($request);

        return new PendingInference(
            execution: InferenceExecution::fromRequest($request),
            driver: $this->driver,
            eventDispatcher: $this->events,
            pricing: $pricing,
        );
    }

    public static function fromConfig(
        LLMConfig $config,
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        $events = EventBusResolver::using($events);
        $driver = (new InferenceDriverFactory($events))->makeDriver(
            config: $config,
            httpClient: self::resolveHttpClient($events, $httpClient),
        );
        return new self(
            driver: $driver,
            events: $events,
            pricingResolver: new StaticPricingResolver($config->getPricing()),
        );
    }

    public static function fromResolver(
        CanResolveLLMConfig $resolver,
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?HttpClient $httpClient = null,
    ): self {
        $events = EventBusResolver::using($events);
        $config = $resolver->resolveConfig();
        $driver = match (true) {
            $resolver instanceof HasExplicitInferenceDriver && $resolver->explicitInferenceDriver() !== null
                => $resolver->explicitInferenceDriver(),
            default => (new InferenceDriverFactory($events))->makeDriver(
                config: $config,
                httpClient: self::resolveHttpClient($events, $httpClient),
            ),
        };

        assert($driver instanceof CanProcessInferenceRequest);
        return new self(
            driver: $driver,
            events: $events,
            pricingResolver: new StaticPricingResolver($config->getPricing()),
        );
    }

    public static function fromProvider(
        LLMProvider $provider,
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
            provider: LLMProvider::dsn($dsn),
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
            provider: LLMProvider::using($preset),
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
