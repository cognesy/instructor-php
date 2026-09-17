<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision;

use Closure;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Logging\EventLog;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\CanCreateDecision;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Contracts\CanProvideDecisionDrivers;
use Cognesy\Polyglot\Decision\Contracts\CanResolveDecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\HasExplicitDecisionDriver;
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Support\Retry\CanDelayRetries;
use Cognesy\Polyglot\Support\Retry\SystemRetryDelay;
use InvalidArgumentException;
use Override;

final class DecisionRuntime implements CanCreateDecision
{
    private readonly CanDelayRetries $retryDelay;

    /** @var Closure():int */
    private readonly Closure $unixTimeReader;

    private readonly string $driverName;

    /** @var (Closure():int)|null */
    private readonly ?Closure $monotonicNanoReader;

    /**
     * @param (callable():int)|null $unixTimeReader
     * @param (callable():int)|null $monotonicNanoReader
     */
    public function __construct(
        private readonly CanProcessDecisionRequest $driver,
        private readonly CanHandleEvents $events,
        private readonly string $defaultModel = '',
        ?CanDelayRetries $retryDelay = null,
        ?callable $unixTimeReader = null,
        string $driverName = '',
        ?callable $monotonicNanoReader = null,
    ) {
        $this->retryDelay = $retryDelay ?? new SystemRetryDelay;
        $this->unixTimeReader = $unixTimeReader === null
            ? static fn (): int => time()
            : Closure::fromCallable($unixTimeReader);
        $this->driverName = trim($driverName) === '' ? get_class($driver) : $driverName;
        $this->monotonicNanoReader = $monotonicNanoReader === null
            ? null
            : Closure::fromCallable($monotonicNanoReader);
    }

    #[Override]
    public function create(DecisionRequest $request): PendingDecision
    {
        $model = $request->model() ?? $this->defaultModel;
        if (trim($model) === '') {
            throw new InvalidArgumentException('Decision model is required.');
        }

        return new PendingDecision(
            request: $request->withModel($model),
            driver: $this->driver,
            retryDelay: $this->retryDelay,
            events: $this->events,
            driverName: $this->driverName,
            unixTimeReader: $this->unixTimeReader,
            monotonicNanoReader: $this->monotonicNanoReader,
        );
    }

    /** @param (callable():int)|null $unixTimeReader */
    public static function fromConfig(
        DecisionConfig $config,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?CanProvideDecisionDrivers $drivers = null,
        ?CanDelayRetries $retryDelay = null,
        ?callable $unixTimeReader = null,
    ): self {
        $events = self::resolveEvents($events);
        $httpClient = self::resolveHttpClient($events, $httpClient);

        return new self(
            driver: self::makeDriver($config, $events, $httpClient, $drivers),
            events: $events,
            defaultModel: $config->model,
            retryDelay: $retryDelay,
            unixTimeReader: $unixTimeReader,
            driverName: $config->driver,
        );
    }

    /** @param (callable():int)|null $unixTimeReader */
    public static function fromProvider(
        DecisionProvider $provider,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?CanProvideDecisionDrivers $drivers = null,
        ?CanDelayRetries $retryDelay = null,
        ?callable $unixTimeReader = null,
    ): self {
        return self::fromResolver(
            resolver: $provider,
            events: $events,
            httpClient: $httpClient,
            drivers: $drivers,
            retryDelay: $retryDelay,
            unixTimeReader: $unixTimeReader,
        );
    }

    /** @param callable(object):void $listener */
    public function onEvent(string $class, callable $listener, int $priority = 0): self
    {
        $this->events->addListener($class, $listener, $priority);

        return $this;
    }

    /** @param callable(object):void $listener */
    public function wiretap(callable $listener): self
    {
        $this->events->wiretap($listener);

        return $this;
    }

    /** @param (callable():int)|null $unixTimeReader */
    private static function fromResolver(
        CanResolveDecisionConfig $resolver,
        ?CanHandleEvents $events,
        ?CanSendHttpRequests $httpClient,
        ?CanProvideDecisionDrivers $drivers,
        ?CanDelayRetries $retryDelay,
        ?callable $unixTimeReader,
    ): self {
        $events = self::resolveEvents($events);
        $config = $resolver->resolveConfig();
        $explicitDriver = $resolver instanceof HasExplicitDecisionDriver
            ? $resolver->explicitDecisionDriver()
            : null;
        $driver = $explicitDriver
            ?? self::makeDriver(
                $config,
                $events,
                self::resolveHttpClient($events, $httpClient),
                $drivers,
            );

        return new self(
            driver: $driver,
            events: $events,
            defaultModel: $config->model,
            retryDelay: $retryDelay,
            unixTimeReader: $unixTimeReader,
            driverName: $config->driver,
        );
    }

    private static function makeDriver(
        DecisionConfig $config,
        CanHandleEvents $events,
        CanSendHttpRequests $httpClient,
        ?CanProvideDecisionDrivers $drivers,
    ): CanProcessDecisionRequest {
        if (trim($config->driver) === '') {
            throw new InvalidArgumentException('Provider type not specified in the Decision configuration.');
        }

        return ($drivers ?? DecisionDriverRegistry::default())->makeDriver(
            $config->driver,
            $config,
            $httpClient,
            $events,
        );
    }

    private static function resolveHttpClient(
        CanHandleEvents $events,
        ?CanSendHttpRequests $httpClient,
    ): CanSendHttpRequests {
        return $httpClient ?? (new HttpClientBuilder(events: $events))->create();
    }

    private static function resolveEvents(?CanHandleEvents $events): CanHandleEvents
    {
        return $events ?? EventLog::root('polyglot.decision.runtime');
    }
}
