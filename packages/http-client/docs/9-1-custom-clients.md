---
title: Custom Clients
description: Add custom HTTP drivers with an explicit registry.
---

## Add a Custom Driver

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Creation\BundledHttpDrivers;

$drivers = BundledHttpDrivers::registry()->withDriver(
    'acme',
    static fn(HttpClientConfig $config, $events, ?object $clientInstance): CanHandleHttpRequest
        => new AcmeHttpDriver($config, $events, $clientInstance),
);
```

## Build a Client with That Driver

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDrivers($drivers)
    ->withConfig(new HttpClientConfig(driver: 'acme'))
    ->create();
```

## Reuse an Existing Vendor Client

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

$client = (new HttpClientBuilder())
    ->withClientInstance('symfony', SymfonyHttpClient::create())
    ->create();
```

## Inject a Driver Directly

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDriver($myDriver) // CanHandleHttpRequest
    ->create();
```

If you need request pooling, use `packages/http-pool`.

## See Also

- [Changing client](7-changing-client.md)
- [Changing client config](8-changing-client-config.md)
- [Middleware](10-middleware.md)
