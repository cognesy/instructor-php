<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;

// Create an event dispatcher with custom listeners
$events = new EventDispatcher();

// Listen for outgoing requests
$events->listen(HttpRequestSent::class, function ($event) {
    echo "Sending {$event->method} request to {$event->url}\n";
    echo "Headers: " . json_encode($event->headers) . "\n";
    echo "Body: " . json_encode($event->body) . "\n";
});

// Listen for incoming responses
$events->listen(HttpResponseReceived::class, function ($event) {
    echo "Received response with status code: {$event->statusCode}\n";
});

// Listen for request failures
$events->listen(HttpRequestFailed::class, function ($event) {
    echo "Request failed: {$event->errors}\n";
    echo "URL: {$event->url}, Method: {$event->method}\n";
});

// Create a client with this event dispatcher
$client = new HttpClient('', $events);
