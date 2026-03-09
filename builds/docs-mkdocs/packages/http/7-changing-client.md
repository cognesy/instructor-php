---
title: 'Changing Client'
description: 'Switch HTTP drivers and runtime clients without changing request code.'
---

## Switch by Typed Config

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\HttpClient;

$client = HttpClient::fromConfig(new HttpClientConfig(driver: 'guzzle'));
$client = HttpClient::fromConfig(new HttpClientConfig(driver: 'symfony'));
// @doctest id="4a4b"
```

Equivalent builder form:

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(driver: 'guzzle'))
    ->create();
// @doctest id="6a1e"
```

## Inject an Explicit Driver

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;

$client = (new HttpClientBuilder())
    ->withDriver(new MockHttpDriver())
    ->create();
// @doctest id="a12a"
```

## Use an Existing Vendor Client Instance

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use GuzzleHttp\Client;

$client = (new HttpClientBuilder())
    ->withClientInstance('guzzle', new Client(['timeout' => 10]))
    ->create();
// @doctest id="0b7b"
```

`withClientInstance()` sets the driver name and passes the instance to that driver.

## See Also

- [Changing client config](8-changing-client-config.md)
- [Custom clients](9-1-custom-clients.md)
- `packages/http-pool/README.md`
