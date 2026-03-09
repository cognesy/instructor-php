---
title: Changing Client Config
description: Configure the client with typed options or a DSN string.
---

Use `HttpClientConfig` when you want readable, typed configuration.

## Typed Config

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$config = new HttpClientConfig(
    driver: 'guzzle',
    connectTimeout: 2,
    requestTimeout: 20,
    streamChunkSize: 512,
    streamHeaderTimeout: 5,
    failOnError: true,
);

$client = (new HttpClientBuilder())
    ->withConfig($config)
    ->create();
```

Core options:

- `driver`
- `connectTimeout`
- `requestTimeout`
- `idleTimeout`
- `streamChunkSize`
- `streamHeaderTimeout`
- `failOnError`

## DSN

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDsn('driver=symfony,connectTimeout=2,requestTimeout=20,streamHeaderTimeout=5,failOnError=true')
    ->create();
```

DSN values are coerced to the typed `HttpClientConfig` fields (`int`, `bool`, `string`).

## Override an Existing Config

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$base = new HttpClientConfig(driver: 'guzzle');
$strict = $base->withOverrides(['failOnError' => true]);

$client = (new HttpClientBuilder())
    ->withConfig($strict)
    ->create();
```

When `withConfig(...)` is provided, that config is authoritative.

## See Also

- [Changing client](7-changing-client.md)
- [Making requests](3-making-requests.md)
- [Handling responses](4-handling-responses.md)
