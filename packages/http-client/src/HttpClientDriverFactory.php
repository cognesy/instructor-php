<?php

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Drivers\GuzzleDriver;
use Cognesy\Http\Drivers\LaravelDriver;
use Cognesy\Http\Drivers\SymfonyDriver;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;

class HttpClientDriverFactory
{
    protected static array $drivers = [];
    protected EventDispatcher $events;

    public function __construct(
        ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Registers a custom HTTP driver with the specified name and closure.
     *
     * @param string $name The name of the driver to register.
     * @param class-string|callable $driver The closure that creates the driver instance, accepting following closure arguments:
     *   - \Cognesy\Http\Data\HttpClientConfig $config: The configuration object for the HTTP client.
     *   - \Cognesy\Utils\Events\EventDispatcher $events: The event dispatcher instance.
     * @return void
     */
    public static function registerDriver(string $name, string|callable $driver): void {
        self::$drivers[$name] = match(true) {
            is_string($driver) => fn($config, $events) => new $driver($config, $events),
            is_callable($driver) => $driver,
            default => throw new InvalidArgumentException("Invalid driver provided for {$name} - must be a class name or callable."),
        };
    }

    /**
     * Creates an HTTP driver instance based on the specified configuration.
     *
     * @param HttpClientConfig $config The configuration object defining the type of HTTP client and its settings.
     * @return CanHandleHttpRequest The instantiated HTTP driver corresponding to the specified client type.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    public function makeDriver(HttpClientConfig $config): CanHandleHttpRequest {
        $name = $config->httpClientType;
        $driverClosure = self::$drivers[$name] ?? $this->defaultDrivers()[$name] ?? null;
        if ($driverClosure === null) {
            throw new InvalidArgumentException("Client not supported: {$name}");
        }
        return $driverClosure($config, $this->events);
    }

    /**
     * Returns the default drivers available for the HTTP client.
     *
     * @return array An associative array of default drivers with their respective configuration closures.
     */
    private function defaultDrivers() : array {
        return [
            'guzzle' => fn($config, $events) => new GuzzleDriver(config: $config, events: $events),
            'symfony' => fn($config, $events) => new SymfonyDriver(config: $config, events: $events),
            'laravel' => fn($config, $events) => new LaravelDriver(config: $config, events: $events),
        ];
    }
}