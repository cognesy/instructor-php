<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Creation;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Contracts\CanProvideDecisionDrivers;
use InvalidArgumentException;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DecisionDriverRegistry implements CanProvideDecisionDrivers
{
    /** @param array<string, string|callable(DecisionConfig,CanSendHttpRequests,EventDispatcherInterface):CanProcessDecisionRequest> $drivers */
    private function __construct(private array $drivers) {}

    public static function make(): self
    {
        return new self([]);
    }

    public static function default(): self
    {
        return new self([
            'typesafe' => 'Cognesy\\Polyglot\\Decision\\Drivers\\TypeSafe\\TypesafeDriver',
        ]);
    }

    /** @param array<string, string|callable(DecisionConfig,CanSendHttpRequests,EventDispatcherInterface):CanProcessDecisionRequest> $drivers */
    public static function fromArray(array $drivers): self
    {
        return new self($drivers);
    }

    /** @param string|callable(DecisionConfig,CanSendHttpRequests,EventDispatcherInterface):CanProcessDecisionRequest $driver */
    public function withDriver(string $name, string|callable $driver): self
    {
        return new self([...$this->drivers, $name => $driver]);
    }

    public function withoutDriver(string $name): self
    {
        $drivers = $this->drivers;
        unset($drivers[$name]);

        return new self($drivers);
    }

    #[Override]
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->drivers);
    }

    #[Override]
    public function driverNames(): array
    {
        return array_keys($this->drivers);
    }

    #[Override]
    public function makeDriver(
        string $name,
        DecisionConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
    ): CanProcessDecisionRequest {
        $definition = $this->drivers[$name]
            ?? throw new InvalidArgumentException("Provider type not supported - missing decision driver: {$name}");
        $driver = is_string($definition)
            ? new $definition($config, $httpClient, $events)
            : $definition($config, $httpClient, $events);
        if (! $driver instanceof CanProcessDecisionRequest) {
            throw new InvalidArgumentException('Decision driver must implement '.CanProcessDecisionRequest::class);
        }

        return $driver;
    }
}
