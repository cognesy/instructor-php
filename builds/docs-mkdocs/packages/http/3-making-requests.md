---
title: 'Making Requests'
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
// @doctest id="1dfb"
```

`string|array` request bodies are supported. Arrays are JSON-encoded. If encoding fails (for example because a value is not serializable), `HttpRequestBody` throws `InvalidArgumentException`.

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
// @doctest id="4d51"
```

## Request Mutation Is Immutable

```php
$request = $request
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->withStreaming(false);
// @doctest id="fe83"
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
// @doctest id="ea7e"
```

## Error Strategy

- `failOnError: false` (default): HTTP 4xx/5xx are returned as regular responses
- `failOnError: true`: driver throws typed HTTP exceptions for 4xx/5xx

> Migration note: default `HttpClientConfig` aligns to `failOnError: false`.
> If you depended on throwing behavior by default, set `failOnError: true` explicitly.
