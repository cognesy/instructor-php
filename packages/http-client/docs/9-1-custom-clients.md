---
title: Custom Clients
description: Register your own drivers when the bundled ones are not enough.
---

For custom integrations, register a driver factory and point the client at that driver name.

## Register a Driver

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Creation\BundledHttpDrivers;
use Cognesy\Events\Contracts\CanHandleEvents;

$drivers = BundledHttpDrivers::registry()->withDriver(
    'acme',
    static fn(HttpClientConfig $config, CanHandleEvents $events, ?object $clientInstance): CanHandleHttpRequest
        => new AcmeHttpDriver($config, $events, $clientInstance),
);
```

## Build a Client

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDrivers($drivers)
    ->withConfig(new HttpClientConfig(driver: 'acme'))
    ->create();
```

## Reuse a Vendor Client

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
