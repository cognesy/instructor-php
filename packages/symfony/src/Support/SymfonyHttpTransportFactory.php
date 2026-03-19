<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Support;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SymfonyHttpTransportFactory
{
    public function create(
        HttpClientConfig $config,
        ?HttpClientInterface $frameworkHttpClient = null,
        ?CanHandleEvents $events = null,
    ): HttpClient {
        $normalizedConfig = $this->normalizeConfig($config);
        $builder = (new HttpClientBuilder($events))->withConfig($normalizedConfig);

        if ($frameworkHttpClient === null || $normalizedConfig->driver !== 'symfony') {
            return $builder->create();
        }

        return $builder
            ->withClientInstance('symfony', $frameworkHttpClient)
            ->create();
    }

    private function normalizeConfig(HttpClientConfig $config): HttpClientConfig
    {
        $driver = match ($config->driver) {
            'framework', 'http_client' => 'symfony',
            default => $config->driver,
        };

        return match (true) {
            $driver === $config->driver => $config,
            default => $config->withOverrides(['driver' => $driver]),
        };
    }
}
