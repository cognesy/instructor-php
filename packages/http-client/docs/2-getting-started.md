---
title: Getting Started
description: 'Quick start for sync requests with mock and real drivers.'
---

## 1. Build a Client

```php
use Cognesy\Http\HttpClient;

$client = HttpClient::default(); // default preset (curl)
```

Use a specific driver preset when needed:

```php
$client = HttpClient::using('guzzle');
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
```

## 3. Add Middleware (Immutable)

```php
$client = $client->withMiddleware($middleware);
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
```

This is the recommended default for deterministic examples and tests.
