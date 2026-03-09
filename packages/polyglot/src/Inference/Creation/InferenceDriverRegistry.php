<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanProvideInferenceDrivers;
use InvalidArgumentException;

final class InferenceDriverRegistry implements CanProvideInferenceDrivers
{
    /** @param array<string, callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest> $drivers */
    private function __construct(
        private array $drivers = [],
    ) {}

    public static function make(): self {
        return new self();
    }

    /**
     * @param array<string, string|callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest> $drivers
     */
    public static function fromArray(array $drivers): self {
        $registry = self::make();

        foreach ($drivers as $name => $driver) {
            $registry = $registry->withDriver($name, $driver);
        }

        return $registry;
    }

    /**
     * @param string|callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest $driver
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
        HttpClient $httpClient,
        CanHandleEvents $events,
    ): CanProcessInferenceRequest {
        $factory = $this->drivers[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException("Provider type not supported - missing inference driver: {$name}");
        }

        return $factory($config, $httpClient, $events);
    }

    /**
     * @param string|callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest $driver
     * @return callable(LLMConfig,HttpClient,CanHandleEvents):CanProcessInferenceRequest
     */
    private static function toDriverFactory(string|callable $driver): callable {
        return match (true) {
            is_callable($driver) => static function (LLMConfig $config, HttpClient $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver factory must return ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
            is_string($driver) => static function (LLMConfig $config, HttpClient $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = new $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver class must implement ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
        };
    }
}
