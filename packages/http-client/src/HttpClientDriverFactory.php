<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzlePool;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Laravel\LaravelPool;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyPool;
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
     * @param class-string|callable $driver The closure that creates the driver instance, accepting following closure arguments:
     *   - HttpClientConfig $config: The configuration object for the HTTP client.
     *   - EventDispatcherInterface $events: The event dispatcher instance.
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
        $config = $config->withOverrides(['driver' => ($driver ?: $config->driver ?: 'guzzle')]);
        $name = $config->driver;
        $driverClosure = self::$drivers[$name] ?? $this->defaultDrivers()[$name] ?? null;

        if ($driverClosure === null) {
            throw new InvalidArgumentException("HTTP client driver supported: {$name}");
        }

        $clientClass = is_null($clientInstance) ? '(auto)' : get_class($clientInstance);
        $this->events->dispatch(new HttpDriverBuilt([
            'clientClass' => $clientClass,
            'config' => $config->toArray(),
        ]));

        return $driverClosure(config: $config, clientInstance: $clientInstance, events: $this->events);
    }

    /**
     * Creates a pool handler instance based on the specified configuration.
     *
     * @param ?HttpClientConfig $config The configuration object defining the type of HTTP client and its settings.
     * @return CanHandleRequestPool The instantiated pool handler corresponding to the specified client type.
     * @throws InvalidArgumentException If the specified client type is not supported for pooling.
     */
    public function makePoolHandler(
        ?HttpClientConfig $config = null,
        ?string $driver = null,
    ): CanHandleRequestPool {
        $config = $config ?? new HttpClientConfig();
        $config = $config->withOverrides(['driver' => ($driver ?: $config->driver ?: 'guzzle')]);
        $name = $config->driver;
        $poolClosure = $this->defaultPoolHandlers()[$name] ?? null;

        if ($poolClosure === null) {
            throw new InvalidArgumentException("HTTP pool handler not supported for driver: {$name}");
        }

        return $poolClosure(config: $config, events: $this->events);
    }

    // INTERNAL ////////////////////////////////////////////////////

    /**
     * Returns the default drivers available for the HTTP client.
     *
     * @return array An associative array of default drivers with their respective configuration closures.
     */
    private function defaultDrivers() : array {
        return [
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
    }

    /**
     * Returns the default pool handlers available for the HTTP client.
     *
     * @return array An associative array of default pool handlers with their respective configuration closures.
     */
    private function defaultPoolHandlers(): array {
        return [
            'guzzle' => fn($config, $events) => new GuzzlePool(
                config: $config,
                client: new \GuzzleHttp\Client(),
                events: $events,
            ),
            'symfony' => fn($config, $events) => new SymfonyPool(
                client: \Symfony\Component\HttpClient\HttpClient::create(),
                config: $config,
                events: $events,
            ),
            'laravel' => fn($config, $events) => new LaravelPool(
                factory: new \Illuminate\Http\Client\Factory(),
                events: $events,
                config: $config,
            ),
        ];
    }
}