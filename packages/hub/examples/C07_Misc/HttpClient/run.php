---
title: 'HTTP Client – Basics'
docname: 'http_client_basics'
---

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

// Basics: build a client, send a request, read status/headers/body.
// We use a Mock driver so this example runs without network.

$client = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        // Respond to a specific request shape
        $mock->addResponse(
            HttpResponse::sync(
                statusCode: 200,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['ok' => true, 'message' => 'Welcome!']),
            ),
            url: 'https://api.example.local/welcome',
            method: 'GET'
        );
    })
    ->create();

$request = new HttpRequest(
    url: 'https://api.example.local/welcome',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->withRequest($request)->get();

echo "Status:  " . $response->statusCode() . "\n";
echo "Headers: " . json_encode($response->headers()) . "\n";
echo "Body:    " . $response->body() . "\n";
?>
```
