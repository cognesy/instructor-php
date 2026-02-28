---
title: Making Requests
description: 'Practical request patterns for sync execution.'
---

## Basic Request Patterns

### GET

```php
$request = new HttpRequest(
    url: 'https://api.example.com/users',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->withRequest($request)->get();
```

### POST JSON

```php
$request = new HttpRequest(
    url: 'https://api.example.com/messages',
    method: 'POST',
    headers: ['Content-Type' => 'application/json'],
    body: ['text' => 'hello'],
    options: [],
);

$response = $client->withRequest($request)->get();
```

## Request Mutation Is Immutable

```php
$request = $request
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->withStreaming(false);
```

Each `with*()` call returns a new request.

## Driver + Config via Builder

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

## Error Strategy

- `failOnError: false` (default): HTTP 4xx/5xx are returned as regular responses
- `failOnError: true`: driver throws typed HTTP exceptions for 4xx/5xx
