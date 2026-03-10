<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Creation;

use Cognesy\HttpPool\Drivers\Curl\Pool\CurlPool;
use Cognesy\HttpPool\Drivers\Guzzle\GuzzlePool;
use Cognesy\HttpPool\Drivers\Symfony\SymfonyPool;
use GuzzleHttp\Client;
use Symfony\Component\HttpClient\HttpClient;

final class BundledHttpPools
{
    public static function registry(): HttpPoolRegistry {
        return HttpPoolRegistry::fromArray([
            'curl' => CurlPool::class,
            'guzzle' => fn($config, $events) => new GuzzlePool(
                config: $config,
                client: new Client(),
                events: $events,
            ),
            'symfony' => fn($config, $events) => new SymfonyPool(
                client: HttpClient::create(),
                config: $config,
                events: $events,
            ),
        ]);
    }
}
