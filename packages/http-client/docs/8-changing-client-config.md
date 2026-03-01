---
title: Changing Client Config
description: Configure driver behavior with typed config or DSN overrides.
---

## Configure via `HttpClientConfig`

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$config = new HttpClientConfig(
    driver: 'guzzle',
    connectTimeout: 2,
    requestTimeout: 20,
    streamChunkSize: 512,
    failOnError: true,
);

$client = (new HttpClientBuilder())
    ->withConfig($config)
    ->create();
```

## Configure via DSN

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDsn('driver=symfony')
    ->create();
```

Use DSN for simple string-based selection (for example `driver`).
For numeric and boolean options, prefer `withConfig(new HttpClientConfig(...))`.

## Preset + Override Pattern

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withPreset('guzzle')
    ->withConfig(new HttpClientConfig(driver: 'guzzle', failOnError: true))
    ->create();
```

When `withConfig(...)` is provided, that config is authoritative.

## Pool and Error Behavior

`HttpClientConfig` also controls:

- `maxConcurrent` and `poolTimeout` for pooling defaults
- `failOnError` for exception-on-4xx/5xx behavior
- `streamChunkSize` for adapter streaming chunk size

## See Also

- [Changing client](7-changing-client.md)
- [Making requests](3-making-requests.md)
- [Handling responses](4-handling-responses.md)
