---
title: Getting Started
description: 'Smallest useful sync request example.'
---

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
```

Use typed config only when you need a specific driver:

```php
use Cognesy\Http\Config\HttpClientConfig;

$client = HttpClient::fromConfig(new HttpClientConfig(driver: 'guzzle'));
```

For tests:

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
