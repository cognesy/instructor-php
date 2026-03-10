<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Creation;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsDrivers;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

final class EmbeddingsDriverRegistry implements CanProvideEmbeddingsDrivers
{
    /** @param array<string, callable(EmbeddingsConfig,CanSendHttpRequests,EventDispatcherInterface):CanHandleVectorization> $drivers */
    private function __construct(
        private array $drivers = [],
    ) {}

    public static function make(): self {
        return new self();
    }

    /**
     * @param array<string, string|callable(EmbeddingsConfig,CanSendHttpRequests,EventDispatcherInterface):CanHandleVectorization> $drivers
     */
    public static function fromArray(array $drivers): self {
        $registry = self::make();

        foreach ($drivers as $name => $driver) {
            $registry = $registry->withDriver($name, $driver);
        }

        return $registry;
    }

    /**
     * @param string|callable(EmbeddingsConfig,CanSendHttpRequests,EventDispatcherInterface):CanHandleVectorization $driver
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
        EmbeddingsConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
    ): CanHandleVectorization {
        $factory = $this->drivers[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException("Provider type not supported - missing embeddings driver: {$name}");
        }

        return $factory($config, $httpClient, $events);
    }

    /**
     * @param string|callable(EmbeddingsConfig,CanSendHttpRequests,EventDispatcherInterface):CanHandleVectorization $driver
     * @return callable(EmbeddingsConfig,CanSendHttpRequests,EventDispatcherInterface):CanHandleVectorization
     */
    private static function toDriverFactory(string|callable $driver): callable {
        return match (true) {
            is_callable($driver) => static function (EmbeddingsConfig $config, CanSendHttpRequests $httpClient, EventDispatcherInterface $events) use ($driver): CanHandleVectorization {
                $instance = $driver($config, $httpClient, $events);
                if (!$instance instanceof CanHandleVectorization) {
                    throw new InvalidArgumentException('Custom embeddings driver factory must return ' . CanHandleVectorization::class);
                }

                return $instance;
            },
            is_string($driver) => static function (EmbeddingsConfig $config, CanSendHttpRequests $httpClient, EventDispatcherInterface $events) use ($driver): CanHandleVectorization {
                $instance = new $driver($config, $httpClient, $events);
                if (!$instance instanceof CanHandleVectorization) {
                    throw new InvalidArgumentException('Custom embeddings driver class must implement ' . CanHandleVectorization::class);
                }

                return $instance;
            },
        };
    }
}
