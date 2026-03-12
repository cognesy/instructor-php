---
title: 'Testing Doubles'
description: 'Deterministic transport testing with the built-in HTTP mock driver.'
---

## Overview

`http-client` uses a real mock, not a fake.

The package-level deterministic testing seam is `MockHttpDriver`, plus the builder
shortcut `withMock()` and the helper factory `MockHttpResponseFactory`.

Use this seam when you want to test:

- request matching and expectations
- response status and body handling
- retries and sequential responses
- streaming and SSE payloads without network calls

## `MockHttpDriver`

`MockHttpDriver` is expectation-driven.

That means it is the right tool when the test needs to say:

- which request should be matched
- how many times it should match
- which response should be returned

It supports:

- fluent expectations through `expect()` and `on()`
- request matching by method, URL, path, headers, body, and stream mode
- sequential replies with `times(...)`
- request inspection through `getReceivedRequests()` and `getLastRequest()`

## `withMock()`

`HttpClientBuilder::withMock()` is the easiest way to build a deterministic client.

Minimal example:

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->expect()
            ->get('https://api.example.com/health')
            ->replyJson(['ok' => true]);
    })
    ->create();
// @doctest id="52a5"
```

Use this for most package and downstream tests.

## `MockHttpResponseFactory`

`MockHttpResponseFactory` helps when a test needs to construct:

- JSON responses
- error responses
- streaming chunk responses
- SSE responses

This is useful when the reply needs more structure than `replyJson(...)` or
`replyText(...)`.

## Which One To Use

Use this rule of thumb:

- `withMock()` for most client-builder tests
- `MockHttpDriver` directly when you need to inspect or reuse the driver object
- `MockHttpResponseFactory` when you need richer reply shapes

For a denser cookbook of examples, see `packages/http-client/MOCK_HTTP_CLIENT.md`.
