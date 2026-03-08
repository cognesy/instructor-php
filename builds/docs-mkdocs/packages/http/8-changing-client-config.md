---
title: 'Changing Client Config'
description: 'Configure driver behavior with typed config or DSN overrides.'
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
    streamHeaderTimeout: 5,
    failOnError: true,
);

$client = (new HttpClientBuilder())
    ->withConfig($config)
    ->create();
// @doctest id="05ed"
```

## Configure via DSN

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDsn('driver=symfony,connectTimeout=2,requestTimeout=20,streamHeaderTimeout=5,failOnError=true')
    ->create();
// @doctest id="924c"
```

DSN values are coerced to the typed `HttpClientConfig` fields (`int`, `bool`, `string`).

## Config + Override Pattern

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$base = new HttpClientConfig(driver: 'guzzle');
$strict = $base->withOverrides(['failOnError' => true]);

$client = (new HttpClientBuilder())
    ->withConfig($strict)
    ->create();
// @doctest id="ea99"
```

When `withConfig(...)` is provided, that config is authoritative.

## Pool and Error Behavior

`HttpClientConfig` also controls:

- `maxConcurrent` and `poolTimeout` for pooling defaults
- `failOnError` for exception-on-4xx/5xx behavior
- `streamChunkSize` for adapter streaming chunk size
- `streamHeaderTimeout` for streaming header priming timeout (curl driver)

## See Also

- [Changing client](7-changing-client.md)
- [Making requests](3-making-requests.md)
- [Handling responses](4-handling-responses.md)
