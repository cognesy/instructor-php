<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;

final class BundledHttpDrivers
{
    /**
     * The bundled table is a compile-time constant, so it is built once per process
     * (instructor-eexl.21, mirroring instructor-eexl.7).
     *
     * This one sits on the `HttpClientRuntime` path, which every implicitly-constructed HTTP
     * client -- and therefore every inference runtime -- traverses.
     *
     * Sharing the instance is safe precisely because `HttpDriverRegistry` is immutable:
     * `withDriver()` and `withoutDriver()` return copies, so a caller that customises the
     * bundled registry gets its own object and cannot reach this one. There is no global
     * registration API that would let anyone mutate it either.
     */
    private static ?HttpDriverRegistry $instance = null;

    public static function registry(): HttpDriverRegistry
    {
        return self::$instance ??= HttpDriverRegistry::fromArray([
            'curl' => CurlDriver::class,
            'guzzle' => GuzzleDriver::class,
            'symfony' => SymfonyDriver::class,
        ]);
    }
}
