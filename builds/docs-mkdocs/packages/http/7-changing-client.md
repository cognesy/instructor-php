---
title: 'Changing Client'
description: 'Switch drivers without changing request code.'
---

You can change how requests are executed without changing how requests are built.

## Choose a Driver

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\HttpClient;

$client = HttpClient::fromConfig(new HttpClientConfig(driver: 'guzzle'));
$client = HttpClient::fromConfig(new HttpClientConfig(driver: 'symfony'));
// @doctest id="d164"
```

The builder gives you the same choice in a more explicit form:

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(driver: 'guzzle'))
    ->create();
// @doctest id="7a43"
```

## Inject a Driver

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;

$client = (new HttpClientBuilder())
    ->withDriver(new MockHttpDriver())
    ->create();
// @doctest id="12b2"
```

`HttpClient::fromDriver($driver)` is the shortest way to wrap a driver directly.

## Reuse a Vendor Client

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use GuzzleHttp\Client;

$client = (new HttpClientBuilder())
    ->withClientInstance('guzzle', new Client(['timeout' => 10]))
    ->create();
// @doctest id="98ca"
```

`withClientInstance()` selects the driver name and passes the vendor client instance to it.

## See Also

- [Changing client config](8-changing-client-config.md)
- [Custom clients](9-1-custom-clients.md)
- `packages/http-pool/README.md`
