<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

use Cognesy\Retrieval\Config\StoreConfig;

interface CanProvideStoreDrivers
{
    public function has(string $name): bool;

    /** @return list<string> */
    public function driverNames(): array;

    public function makeDriver(string $name, StoreConfig $config): CanStoreDocuments;
}
