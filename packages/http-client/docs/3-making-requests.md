---
title: Making Requests
description: 'Short request patterns.'
---

### GET

```php
$request = new HttpRequest(
    url: 'https://api.example.com/users',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->send($request)->get();
```

Arrays are JSON-encoded automatically.

### POST JSON

```php
$request = new HttpRequest(
    url: 'https://api.example.com/messages',
    method: 'POST',
    headers: ['Content-Type' => 'application/json'],
    body: ['text' => 'hello'],
    options: [],
);

$response = $client->send($request)->get();
```

### Immutable mutation

```php
$request = $request
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->withStreaming(false);
```

### Build with config

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(
        driver: 'guzzle',
        connectTimeout: 5,
        requestTimeout: 30,
        failOnError: true,
    ))
    ->create();
```
