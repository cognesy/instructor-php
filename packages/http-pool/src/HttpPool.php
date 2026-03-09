<?php declare(strict_types=1);

namespace Cognesy\HttpPool;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\Contracts\CanHandleRequestPool;
use Cognesy\HttpPool\Contracts\CanProvideHttpPools;
use Cognesy\HttpPool\Creation\HttpPoolBuilder;

final class HttpPool
{
    public function __construct(
        private readonly CanHandleRequestPool $poolHandler,
        private readonly HttpPoolConfig $config,
        private readonly CanHandleEvents $events,
    ) {}

    public static function default(): self {
        return (new HttpPoolBuilder())->create();
    }

    public static function fromConfig(
        HttpPoolConfig $config,
        ?CanProvideHttpPools $pools = null,
    ): self {
        $builder = (new HttpPoolBuilder())->withConfig($config);

        return match (true) {
            $pools !== null => $builder->withPools($pools)->create(),
            default => $builder->create(),
        };
    }

    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
        return $this->poolHandler->pool($requests, $maxConcurrent);
    }

    public function withRequests(HttpRequestList $requests): PendingHttpPool {
        return new PendingHttpPool($requests, $this->poolHandler);
    }

    public function config(): HttpPoolConfig {
        return $this->config;
    }

    public function events(): CanHandleEvents {
        return $this->events;
    }
}
