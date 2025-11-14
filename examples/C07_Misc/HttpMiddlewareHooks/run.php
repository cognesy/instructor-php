---
title: 'HTTP Middleware (Hooks + Conditional Decoration)'
docname: 'http_middleware_hooks'
---

<?php
require 'examples/boot.php';

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

// Scenario: Demonstrate BaseMiddleware hooks and conditional decoration

class HooksExampleMiddleware extends BaseMiddleware
{
    protected function beforeRequest(HttpRequest $request): HttpRequest {
        // attach a request-id header
        return $request->withHeader('X-Request-ID', 'hooks-demo-123');
    }

    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse {
        // log status to stdout (example side-effect)
        fwrite(STDOUT, "afterRequest: status=" . $response->statusCode() . "\n");
        return $response;
    }

    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response): bool {
        // only decorate streamed responses
        return $response->isStreamed();
    }

    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse {
        // decorate: number each emitted chunk
        return new class($request, $response) extends BaseResponseDecorator {
            private int $i = 0;
            protected function chunkMap(string $chunk): string {
                $this->i += 1;
                return sprintf("[%02d] %s", $this->i, $chunk);
            }
        };
    }
}

// Mock driver returns a streamed response (full lines as chunks for simplicity)
$driver = new MockHttpDriver();
$driver->addResponse(
    new HttpResponse(
        statusCode: 200,
        body: '',
        headers: ['Content-Type' => 'text/plain'],
        isStreamed: true,
        stream: ["alpha\n", "beta\n", "gamma\n"],
    ),
    url: 'https://api.example.local/lines',
    method: 'GET'
);

$client = new HttpClient(driver: $driver);
$client = $client->withMiddleware(new HooksExampleMiddleware());

$request = new HttpRequest(
    url: 'https://api.example.local/lines',
    method: 'GET',
    headers: ['Accept' => 'text/plain'],
    body: '',
    options: ['stream' => true],
);

foreach ($client->withRequest($request)->stream() as $chunk) {
    echo $chunk; // chunks are numbered by middleware
}
?>

