<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Creation;

use Cognesy\Retrieval\Config\StoreProvider;
use Cognesy\Retrieval\Contracts\CanProvideStoreDrivers;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;

final class StoreFactory
{
    public static function fromProvider(
        StoreProvider $provider,
        ?CanProvideStoreDrivers $drivers = null,
    ): CanStoreDocuments {
        return $provider->explicitDriver()
            ?? ($drivers ?? StoreDriverRegistry::default())->makeDriver(
                $provider->config()->driver,
                $provider->config(),
            );
    }
}
