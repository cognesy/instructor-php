<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;

final class BundledHttpDrivers
{
    public static function registry(): HttpDriverRegistry
    {
        return HttpDriverRegistry::fromArray([
            'curl' => CurlDriver::class,
            'guzzle' => GuzzleDriver::class,
            'symfony' => SymfonyDriver::class,
        ]);
    }
}
