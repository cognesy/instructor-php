<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Contracts;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Config\HttpClientConfig;

interface CanProvideHttpPools
{
    public function has(string $name): bool;

    /** @return array<string> */
    public function poolNames(): array;

    public function makePool(
        string $name,
        HttpClientConfig $config,
        CanHandleEvents $events,
    ): CanHandleRequestPool;
}
