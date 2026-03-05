---
title: 'Getting Started'
description: 'Quick start for sync requests with mock and real drivers.'
---

## 1. Build a Client

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\HttpClient;

$client = HttpClient::default(); // default driver from HttpClientConfig defaults (curl)
// @doctest id="7321"
```

Select a specific driver via typed config when needed:

```php
$client = HttpClient::fromConfig(new HttpClientConfig(driver: 'guzzle'));
// @doctest id="0bf1"
```

## 2. Create and Execute a Request

```php
use Cognesy\Http\Data\HttpRequest;

$request = new HttpRequest(
    url: 'https://api.example.com/health',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->withRequest($request)->get();

echo $response->statusCode();
echo $response->body();
// @doctest id="8c21"
```

## 3. Add Middleware (Immutable)

```php
$client = $client->withMiddleware($middleware);
// @doctest id="5a49"
```

Do not rely on in-place mutation.

## 4. Fast Local Testing with Mock Driver

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpResponse;

$client = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->addResponse(
            HttpResponse::sync(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
            url: 'https://api.example.com/health',
            method: 'GET'
        );
    })
    ->create();
// @doctest id="71c4"
```

This is the recommended default for deterministic examples and tests.
