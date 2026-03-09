---
title: 'Getting Started'
description: 'Start with a single request, then add config or mocks when needed.'
---

Create a client and send a request:

```php
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;

$client = HttpClient::default();

$response = $client->send(new HttpRequest(
    url: 'https://api.example.com/health',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
))->get();

echo $response->statusCode();
echo $response->body();
// @doctest id="fe63"
```

Use `HttpClientConfig` when you want a specific driver or timeout profile:

```php
use Cognesy\Http\Config\HttpClientConfig;

$client = HttpClient::fromConfig(new HttpClientConfig(driver: 'guzzle'));
// @doctest id="815b"
```

For tests, use the builder with the mock driver:

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
// @doctest id="87ca"
```
