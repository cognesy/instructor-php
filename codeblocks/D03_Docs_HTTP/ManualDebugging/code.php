<?php declare(strict_types=1);

try {
    echo "Sending request to: {$request->url()}\n";
    echo "Headers: " . json_encode($request->headers()) . "\n";
    echo "Body: " . $request->body()->toString() . "\n";

    $response = $client->withRequest($request)->get();

    echo "Response status: {$response->statusCode()}\n";
    echo "Response headers: " . json_encode($response->headers()) . "\n";
    echo "Response body: {$response->body()}\n";
} catch (RequestException $e) {
    echo "Error: {$e->getMessage()}\n";
    if ($e->getPrevious()) {
        echo "Original error: {$e->getPrevious()->getMessage()}\n";
    }
}
