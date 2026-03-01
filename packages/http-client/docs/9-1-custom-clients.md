---
title: Custom Clients
description: Register custom drivers and pool handlers for non-standard transports.
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

## Register a Custom Pool Handler (Optional)

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Creation\HttpClientDriverFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

HttpClientDriverFactory::registerPoolHandler(
    'acme',
    static function (HttpClientConfig $config, EventDispatcherInterface $events): CanHandleRequestPool {
        return new AcmePoolHandler($config, $events);
    }
);
```

Register this when your custom driver should support `HttpClient::pool()`.

If you skip this, `pool()` fails for that custom driver.

## Reuse Existing Vendor Clients

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

$client = (new HttpClientBuilder())
    ->withClientInstance('symfony', SymfonyHttpClient::create())
    ->create();
```

## Direct Driver Injection (No Global Registration)

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDriver($myDriver) // CanHandleHttpRequest
    ->withPoolHandler($myPoolHandler) // CanHandleRequestPool (optional)
    ->create();
```

## See Also

- [Changing client](7-changing-client.md)
- [Changing client config](8-changing-client-config.md)
- [Middleware](10-middleware.md)
