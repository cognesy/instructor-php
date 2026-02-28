---
title: 'HTTP Middleware (Hooks)'
docname: 'http_middleware_hooks'
id: 'b6fd'
---
## Overview

Practical `BaseMiddleware` example: enrich request headers in `beforeRequest()` and
perform lightweight side effects in `afterRequest()`.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

class RequestIdMiddleware extends BaseMiddleware
{
    protected function beforeRequest(HttpRequest $request): HttpRequest {
        return $request->withHeader('X-Request-ID', 'req-demo-123');
    }

    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse {
        fwrite(STDOUT, "status=" . $response->statusCode() . "\n");
        return $response;
    }
}

$driver = new MockHttpDriver();
$driver->addResponse(
    HttpResponse::sync(
        statusCode: 200,
        headers: ['Content-Type' => 'application/json'],
        body: json_encode(['ok' => true]),
    ),
    url: 'https://api.example.local/ping',
    method: 'GET'
);

$client = (new HttpClient(driver: $driver))
    ->withMiddleware(new RequestIdMiddleware());

$request = new HttpRequest(
    url: 'https://api.example.local/ping',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->withRequest($request)->get();
echo $response->body() . "\n";
?>
```
