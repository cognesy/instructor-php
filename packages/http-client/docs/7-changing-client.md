---
title: Changing Client
description: Switch HTTP drivers and runtime clients without changing request code.
---

## Switch by Preset

```php
use Cognesy\Http\HttpClient;

$client = HttpClient::using('guzzle');
$client = HttpClient::using('symfony');
```

Equivalent builder form:

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withPreset('guzzle')
    ->create();
```

## Inject an Explicit Driver

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;

$client = (new HttpClientBuilder())
    ->withDriver(new MockHttpDriver())
    ->create();
```

## Use an Existing Vendor Client Instance

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use GuzzleHttp\Client;

$client = (new HttpClientBuilder())
    ->withClientInstance('guzzle', new Client(['timeout' => 10]))
    ->create();
```

`withClientInstance()` sets the driver name and passes the instance to that driver.

## Override Pool Handling for Custom Drivers

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withDriver($customDriver)
    ->withPoolHandler($customPoolHandler)
    ->create();
```

Use this when your custom driver cannot use built-in pooling adapters.

## See Also

- [Changing client config](8-changing-client-config.md)
- [Custom clients](9-1-custom-clients.md)
- [Request pooling](6-pooling.md)
