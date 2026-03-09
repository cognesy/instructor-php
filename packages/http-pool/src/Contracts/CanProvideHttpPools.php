<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Contracts;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\HttpPool\Config\HttpPoolConfig;

interface CanProvideHttpPools
{
    public function has(string $name): bool;

    /** @return array<string> */
    public function poolNames(): array;

    public function makePool(
        string $name,
        HttpPoolConfig $config,
        CanHandleEvents $events,
    ): CanHandleRequestPool;
}
