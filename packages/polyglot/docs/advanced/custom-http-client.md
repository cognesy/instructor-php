---
title: Customizing HTTP Client
description: 'Learn how to use custom HTTP clients in Polyglot.'
---

Polyglot allows you to use custom HTTP clients for specific connection requirements:

```php
<?php
use Cognesy\Http\Config\HttpClientConfig;use Cognesy\Http\HttpClient;use Cognesy\Polyglot\Inference\Inference;use Cognesy\Polyglot\Inference\InferenceRuntime;

// Create a custom HTTP client configuration
$httpConfig = new HttpClientConfig(
    connectTimeout: 5,      // 5 seconds connection timeout
    requestTimeout: 60,     // 60 seconds request timeout
    idleTimeout: 120,       // 120 seconds idle timeout for streaming
    maxConcurrent: 10,      // Maximum 10 concurrent requests
    failOnError: true,      // Throw exceptions on HTTP errors
);

// Create a custom HTTP client
$httpClient = new HttpClient('guzzle', $httpConfig);

// Use the custom HTTP client with Inference
$inference = Inference::fromRuntime(InferenceRuntime::using(
    preset: 'openai',
    httpClient: $httpClient,
));

// Make a request with the custom HTTP client
$response = $inference->with(
    messages: 'This request uses a custom HTTP client.'
)->get();

echo $response;
```
