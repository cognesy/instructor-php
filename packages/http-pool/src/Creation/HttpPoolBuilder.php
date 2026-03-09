<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\HttpPool\Contracts\CanHandleRequestPool;
use Cognesy\HttpPool\Contracts\CanProvideHttpPools;
use Cognesy\HttpPool\HttpPool;
use InvalidArgumentException;

final class HttpPoolBuilder
{
    private CanHandleEvents $events;
    private ?HttpClientConfig $config = null;
    private ?CanHandleRequestPool $poolHandler = null;
    private ?CanProvideHttpPools $pools = null;

    public function __construct(
        ?CanHandleEvents $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher(name: 'http.pool.builder');
    }

    public function withConfig(HttpClientConfig $config): self {
        $this->config = $config;
        return $this;
    }

    public function withDsn(string $dsn): self {
        $this->config = HttpClientConfig::fromDsn($dsn);
        return $this;
    }

    public function withPoolHandler(CanHandleRequestPool $poolHandler): self {
        $this->poolHandler = $poolHandler;
        return $this;
    }

    public function withPools(CanProvideHttpPools $pools): self {
        $this->pools = $pools;
        return $this;
    }

    public function withEventBus(CanHandleEvents $events): self {
        $this->events = $events;
        return $this;
    }

    public function create(): HttpPool {
        $config = $this->config ?? new HttpClientConfig(driver: 'curl');
        $handler = $this->poolHandler ?? $this->buildPoolHandler($config);

        return new HttpPool(
            poolHandler: $handler,
            config: $config,
            events: $this->events,
        );
    }

    private function buildPoolHandler(HttpClientConfig $config): CanHandleRequestPool {
        $name = $config->driver ?: 'curl';
        $registry = $this->pools ?? BundledHttpPools::registry();

        if (!$registry->has($name)) {
            throw new InvalidArgumentException("Unknown pool handler: {$name}");
        }

        return $registry->makePool($name, $config, $this->events);
    }
}
