---
title: 'HTTP Middleware (Sync)'
docname: 'http_middleware_sync'
id: 'bd7e'
---
## Overview

Demonstrates synchronous HTTP middleware that adds a request header and transforms the
response body by uppercasing a JSON field.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

// Scenario: Add a request header and uppercase a JSON field in a sync response

class UppercaseBodyMiddleware extends BaseMiddleware
{
    protected function beforeRequest(HttpRequest $request): HttpRequest {
        // add a trace header
        return $request->withHeader('X-Trace', 'sync-demo');
    }

    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse {
        // transform JSON body by uppercasing the "message" field
        $body = $response->body();
        $data = json_decode($body, true) ?? [];
        if (isset($data['message']) && is_string($data['message'])) {
            $data['message'] = strtoupper($data['message']);
            return HttpResponse::sync(
                statusCode: $response->statusCode(),
                headers: $response->headers(),
                body: json_encode($data),
            );
        }
        return $response;
    }
}

// Mock driver returns a simple JSON payload
$driver = new MockHttpDriver();
$driver->addResponse(
    HttpResponse::sync(
        statusCode: 200,
        headers: ['Content-Type' => 'application/json'],
        body: json_encode(['message' => 'hello']),
    ),
    url: 'https://api.example.local/echo',
    method: 'POST'
);

$client = new HttpClient(driver: $driver);
$client = $client->withMiddleware(new UppercaseBodyMiddleware());

$request = new HttpRequest(
    url: 'https://api.example.local/echo',
    method: 'POST',
    headers: ['Accept' => 'application/json'],
    body: ['message' => 'hello'],
    options: [],
);

$response = $client->withRequest($request)->get();

echo "Status: {$response->statusCode()}\n";
echo "Body:   {$response->body()}\n";
?>
```
