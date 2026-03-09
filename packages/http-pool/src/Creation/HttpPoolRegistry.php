<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\Contracts\CanHandleRequestPool;
use Cognesy\HttpPool\Contracts\CanProvideHttpPools;
use InvalidArgumentException;

final class HttpPoolRegistry implements CanProvideHttpPools
{
    /** @param array<string, callable(HttpPoolConfig,CanHandleEvents):CanHandleRequestPool> $pools */
    private function __construct(
        private array $pools = [],
    ) {}

    public static function make(): self {
        return new self();
    }

    /**
     * @param array<string, string|callable(HttpPoolConfig,CanHandleEvents):CanHandleRequestPool> $pools
     */
    public static function fromArray(array $pools): self {
        $registry = self::make();

        foreach ($pools as $name => $pool) {
            $registry = $registry->withPool($name, $pool);
        }

        return $registry;
    }

    /**
     * @param string|callable(HttpPoolConfig,CanHandleEvents):CanHandleRequestPool $pool
     */
    public function withPool(string $name, string|callable $pool): self {
        $copy = clone $this;
        $copy->pools[$name] = self::toPoolFactory($pool);
        return $copy;
    }

    public function withoutPool(string $name): self {
        $copy = clone $this;
        unset($copy->pools[$name]);
        return $copy;
    }

    #[\Override]
    public function has(string $name): bool {
        return isset($this->pools[$name]);
    }

    /** @return array<string> */
    #[\Override]
    public function poolNames(): array {
        return array_keys($this->pools);
    }

    #[\Override]
    public function makePool(
        string $name,
        HttpPoolConfig $config,
        CanHandleEvents $events,
    ): CanHandleRequestPool {
        $factory = $this->pools[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException("Unknown pool handler: {$name}");
        }

        return $factory($config, $events);
    }

    /**
     * @param string|callable(HttpPoolConfig,CanHandleEvents):CanHandleRequestPool $pool
     * @return callable(HttpPoolConfig,CanHandleEvents):CanHandleRequestPool
     */
    private static function toPoolFactory(string|callable $pool): callable {
        return match (true) {
            is_callable($pool) => static function (HttpPoolConfig $config, CanHandleEvents $events) use ($pool): CanHandleRequestPool {
                $instance = $pool($config, $events);
                if (!$instance instanceof CanHandleRequestPool) {
                    throw new InvalidArgumentException('Custom HTTP pool factory must return ' . CanHandleRequestPool::class);
                }

                return $instance;
            },
            is_string($pool) => static function (HttpPoolConfig $config, CanHandleEvents $events) use ($pool): CanHandleRequestPool {
                $instance = new $pool($config, $events);
                if (!$instance instanceof CanHandleRequestPool) {
                    throw new InvalidArgumentException('Custom HTTP pool class must implement ' . CanHandleRequestPool::class);
                }

                return $instance;
            },
        };
    }
}
