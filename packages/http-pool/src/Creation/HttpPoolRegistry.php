<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\Contracts\CanHandleRequestPool;
use Cognesy\HttpPool\Contracts\CanProvideHttpPools;
use Cognesy\HttpPool\Drivers\Curl\Pool\CurlPool;
use Cognesy\HttpPool\Drivers\Guzzle\GuzzlePool;
use Cognesy\HttpPool\Drivers\Symfony\SymfonyPool;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Override;
use Symfony\Component\HttpClient\HttpClient;

final class HttpPoolRegistry implements CanProvideHttpPools
{
    private static ?self $default = null;

    /** @param array<string, callable(HttpPoolConfig,CanHandleEvents):CanHandleRequestPool> $pools */
    private function __construct(
        private array $pools = [],
    ) {}

    public static function make(): self {
        return new self();
    }

    public static function default(): self {
        return self::$default ??= self::fromArray([
            'curl' => CurlPool::class,
            'guzzle' => static fn (HttpPoolConfig $config, CanHandleEvents $events): CanHandleRequestPool => new GuzzlePool(
                config: $config,
                client: new Client(),
                events: $events,
            ),
            'symfony' => static fn (HttpPoolConfig $config, CanHandleEvents $events): CanHandleRequestPool => new SymfonyPool(
                client: HttpClient::create(),
                config: $config,
                events: $events,
            ),
        ]);
    }

    /**
     * @param array<string, string|callable(HttpPoolConfig,CanHandleEvents):CanHandleRequestPool> $pools
     */
    public static function fromArray(array $pools): self {
        $factories = [];
        foreach ($pools as $name => $pool) {
            $factories[$name] = self::toPoolFactory($pool);
        }

        return new self($factories);
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

    #[Override]
    public function has(string $name): bool {
        return isset($this->pools[$name]);
    }

    /** @return array<string> */
    #[Override]
    public function poolNames(): array {
        return array_keys($this->pools);
    }

    #[Override]
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
