<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Closure;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Drivers\ExtHttp\ExtHttpDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Events\HttpDriverBuilt;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class HttpClientDriverFactory
{
    protected static array $drivers = [];

    protected EventDispatcherInterface $events;

    public function __construct(
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
    }

    /**
     * Registers a custom HTTP driver with the specified name and closure.
     *
     * @param string $name The name of the driver to register.
     * @param class-string|callable(HttpClientConfig, EventDispatcherInterface): CanHandleHttpRequest $driver The closure that creates the driver instance, accepting following closure arguments:
     *   - HttpClientConfig $config: The configuration object for the HTTP client.
     *   - EventDispatcherInterface $events: The event dispatcher instance.
     * @return void
     */
    public static function registerDriver(string $name, string|callable $driver): void {
        self::$drivers[$name] = match(true) {
            is_string($driver) => fn($config, $events) => new $driver($config, $events),
            default => $driver,
        };
    }

    /**
     * Creates an HTTP driver instance based on the specified configuration.
     *
     * @param ?HttpClientConfig $config The configuration object defining the type of HTTP client and its settings.
     * @return CanHandleHttpRequest The instantiated HTTP driver corresponding to the specified client type.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    public function makeDriver(
        ?HttpClientConfig $config = null,
        ?string $driver = null,
        ?object $clientInstance = null,
    ): CanHandleHttpRequest {
        $config = $config ?? new HttpClientConfig();
        $config = $config->withOverrides(['driver' => ($driver ?: $config->driver ?: 'curl')]);
        $name = $config->driver;

        $driverClosure = self::$drivers[$name] ?? $this->defaultDrivers($name);

        $clientClass = is_null($clientInstance) ? '(auto)' : get_class($clientInstance);
        $this->events->dispatch(new HttpDriverBuilt([
            'clientClass' => $clientClass,
            'config' => $config->toArray(),
        ]));

        return $driverClosure($config, $this->events, $clientInstance);
    }
    // INTERNAL ////////////////////////////////////////////////////

    /**
     * Returns the default driver closure for the specified driver name.
     *
     * @return Closure(HttpClientConfig, EventDispatcherInterface, ?object): CanHandleHttpRequest
     */
    private function defaultDrivers(string $name) : Closure {
        $drivers = [
            'curl' => fn($config, $events, $clientInstance) => new CurlDriver(
                config: $config,
                events: $events,
                clientInstance: $clientInstance,
            ),
            'exthttp' => fn($config, $events, $clientInstance) => new ExtHttpDriver(
                config: $config,
                events: $events,
                clientInstance: $clientInstance,
            ),
            'guzzle' => fn($config, $events, $clientInstance) => new GuzzleDriver(
                config: $config,
                events: $events,
                clientInstance: $clientInstance,
            ),
            'symfony' => fn($config, $events, $clientInstance) => new SymfonyDriver(
                config: $config,
                events: $events,
                clientInstance: $clientInstance,
            ),
            'laravel' => fn($config, $events, $clientInstance) => new LaravelDriver(
                config: $config,
                events: $events,
                clientInstance: $clientInstance,
            ),
        ];
        return $drivers[$name] ?? throw new InvalidArgumentException("Unknown driver: {$name}");
    }

}
