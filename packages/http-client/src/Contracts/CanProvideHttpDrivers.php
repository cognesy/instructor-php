<?php declare(strict_types=1);

namespace Cognesy\Http\Contracts;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Config\HttpClientConfig;

interface CanProvideHttpDrivers
{
    public function has(string $name): bool;

    /** @return array<string> */
    public function driverNames(): array;

    public function makeDriver(
        string $name,
        HttpClientConfig $config,
        CanHandleEvents $events,
        ?object $clientInstance = null,
    ): CanHandleHttpRequest;
}
