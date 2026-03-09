---
title: Custom Clients
description: Register custom HTTP request drivers for non-standard transports.
---

## Register a Custom Driver

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Creation\HttpClientDriverFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

HttpClientDriverFactory::registerDriver(
    'acme',
    static function (HttpClientConfig $config, EventDispatcherInterface $events): CanHandleHttpRequest {
        return new AcmeHttpDriver($config, $events);
    }
);
```

## Build Client with Custom Driver

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(driver: 'acme'))
    ->create();
```

## Reuse Existing Vendor Clients

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

$client = (new HttpClientBuilder())
    ->withClientInstance('symfony', SymfonyHttpClient::create())
    ->create();
```

## Direct Driver Injection

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDriver($myDriver) // CanHandleHttpRequest
    ->create();
```

If you need concurrent request execution, use the dedicated `packages/http-pool` APIs.

## See Also

- [Changing client](7-changing-client.md)
- [Changing client config](8-changing-client-config.md)
- [Middleware](10-middleware.md)
