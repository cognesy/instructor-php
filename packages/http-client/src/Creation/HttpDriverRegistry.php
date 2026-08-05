<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanProvideHttpDrivers;
use InvalidArgumentException;

final class HttpDriverRegistry implements CanProvideHttpDrivers
{
    /** @param array<string, callable(HttpClientConfig,CanHandleEvents,?object):CanHandleHttpRequest> $drivers */
    private function __construct(
        private array $drivers = [],
    ) {}

    public static function make(): self
    {
        return new self();
    }

    /**
     * Populates the factory table directly rather than folding the public wither over the map
     * (instructor-eexl.21). The wither clones the registry per entry, which is correct for a
     * wither and wrong for building a table. Same defect and same fix as
     * `InferenceDriverRegistry` (instructor-eexl.7).
     *
     * @param array<string, string|callable(HttpClientConfig,CanHandleEvents,?object):CanHandleHttpRequest> $drivers
     */
    public static function fromArray(array $drivers): self
    {
        $factories = [];
        foreach ($drivers as $name => $driver) {
            $factories[$name] = self::toDriverFactory($driver);
        }

        return new self($factories);
    }

    /**
     * @param string|callable(HttpClientConfig,CanHandleEvents,?object):CanHandleHttpRequest $driver
     */
    public function withDriver(string $name, string|callable $driver): self
    {
        $copy = clone $this;
        $copy->drivers[$name] = self::toDriverFactory($driver);
        return $copy;
    }

    public function withoutDriver(string $name): self
    {
        $copy = clone $this;
        unset($copy->drivers[$name]);
        return $copy;
    }

    #[\Override]
    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /** @return array<string> */
    #[\Override]
    public function driverNames(): array
    {
        return array_keys($this->drivers);
    }

    #[\Override]
    public function makeDriver(
        string $name,
        HttpClientConfig $config,
        CanHandleEvents $events,
        ?object $clientInstance = null,
    ): CanHandleHttpRequest {
        $factory = $this->drivers[$name] ?? null;

        if ($factory === null) {
            throw new InvalidArgumentException("Unknown HTTP driver: {$name}");
        }

        return $factory($config, $events, $clientInstance);
    }

    /**
     * @param string|callable(HttpClientConfig,CanHandleEvents,?object):CanHandleHttpRequest $driver
     * @return callable(HttpClientConfig,CanHandleEvents,?object):CanHandleHttpRequest
     */
    private static function toDriverFactory(string|callable $driver): callable
    {
        return match (true) {
            is_callable($driver) => static function (HttpClientConfig $config, CanHandleEvents $events, ?object $clientInstance) use ($driver): CanHandleHttpRequest {
                $instance = $driver($config, $events, $clientInstance);
                if (!$instance instanceof CanHandleHttpRequest) {
                    throw new InvalidArgumentException('Custom HTTP driver factory must return ' . CanHandleHttpRequest::class);
                }

                return $instance;
            },
            is_string($driver) => static function (HttpClientConfig $config, CanHandleEvents $events, ?object $clientInstance) use ($driver): CanHandleHttpRequest {
                $instance = new $driver($config, $events, $clientInstance);
                if (!$instance instanceof CanHandleHttpRequest) {
                    throw new InvalidArgumentException('Custom HTTP driver class must implement ' . CanHandleHttpRequest::class);
                }

                return $instance;
            },
        };
    }
}
