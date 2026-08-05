<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanProvideInferenceDrivers;
use InvalidArgumentException;

final class InferenceDriverRegistry implements CanProvideInferenceDrivers
{
    /** @param array<string, callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest> $drivers */
    private function __construct(
        private array $drivers = [],
    ) {}

    public static function make(): self {
        return new self();
    }

    /**
     * Populates the table directly rather than folding `withDriver()` over it.
     *
     * `withDriver()` is a public wither and clones per call, which is correct for a wither and
     * wrong for a named constructor: building the 29-entry bundled map through it cost 29
     * clones of a growing array to produce one registry. This is the same object either way —
     * `toDriverFactory()` is still the only thing that turns an entry into a factory.
     *
     * @param array<string, string|callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest> $drivers
     */
    public static function fromArray(array $drivers): self {
        $factories = [];
        foreach ($drivers as $name => $driver) {
            $factories[$name] = self::toDriverFactory($driver);
        }

        return new self($factories);
    }

    /**
     * @param string|callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest $driver
     */
    public function withDriver(string $name, string|callable $driver): self {
        $copy = clone $this;
        $copy->drivers[$name] = self::toDriverFactory($driver);
        return $copy;
    }

    public function withoutDriver(string $name): self {
        $copy = clone $this;
        unset($copy->drivers[$name]);
        return $copy;
    }

    #[\Override]
    public function has(string $name): bool {
        return isset($this->drivers[$name]);
    }

    /** @return array<string> */
    #[\Override]
    public function driverNames(): array {
        return array_keys($this->drivers);
    }

    #[\Override]
    public function makeDriver(
        string $name,
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        CanHandleEvents $events,
    ): CanProcessInferenceRequest {
        $factory = $this->drivers[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException("Provider type not supported - missing inference driver: {$name}");
        }

        return $factory($config, $httpClient, $events);
    }

    /**
     * @param string|callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest $driver
     * @return callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest
     */
    private static function toDriverFactory(string|callable $driver): callable {
        return match (true) {
            is_callable($driver) => static function (LLMConfig $config, CanSendHttpRequests $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver factory must return ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
            is_string($driver) => static function (LLMConfig $config, CanSendHttpRequests $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = new $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver class must implement ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
        };
    }
}
