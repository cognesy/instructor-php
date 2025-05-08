---
title: Extending Polyglot
description: 'Learn how to extend Polyglot with custom providers and middleware.'
---


Understanding Polyglot's architecture makes it easier to extend the library to support new providers or add new functionality.

## Adding a New LLM Provider

To add support for a new LLM provider, you need to implement several components:

1. **Message Format Adapter**: Implements `CanMapMessages` to convert Polyglot's message format to the provider's format
2. **Body Format Adapter**: Implements `CanMapRequestBody` to structure the request body according to the provider's API
3. **Request Adapter**: Implements `ProviderRequestAdapter` to build HTTP requests for the provider
4. **Response Adapter**: Implements `ProviderResponseAdapter` to parse responses from the provider
5. **Usage Format Adapter**: Implements `CanMapUsage` to extract token usage information

Then, you need to modify the `InferenceDriverFactory` to create the appropriate driver for your provider:

```php
// In InferenceDriverFactory
public function newProvider(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
    return new ModularLLMDriver(
        $config,
        new NewProviderRequestAdapter(
            $config,
            new NewProviderBodyFormat($config, new NewProviderMessageFormat())
        ),
        new NewProviderResponseAdapter(new NewProviderUsageFormat()),
        $httpClient,
        $events
    );
}
```

Finally, add your provider to the `make` method's match statement.



## Adding a New Embeddings Provider

Similarly, to add a new embeddings provider, implement the `CanVectorize` interface:

```php
namespace Cognesy\Polyglot\Embeddings\Drivers;

class NewEmbeddingsDriver implements CanVectorize {
    public function __construct(
        protected EmbeddingsConfig $config,
        protected ?CanHandleHttpRequest $httpClient = null,
        protected ?EventDispatcher $events = null
    ) { ... }

    public function vectorize(array $input, array $options = []): EmbeddingsResponse { ... }

    protected function getEndpointUrl(): string { ... }
    protected function getRequestHeaders(): array { ... }
    protected function getRequestBody(array $input, array $options): array { ... }
    protected function toResponse(array $response): EmbeddingsResponse { ... }
    protected function makeUsage(array $response): Usage { ... }
}
```

Then, modify the `Embeddings` class to create your driver:

```php
// In Embeddings::getDriver
protected function getDriver(EmbeddingsConfig $config, CanHandleHttpRequest $httpClient): CanVectorize {
    return match ($config->providerType) {
        // Existing providers...
        'new-provider' => new NewEmbeddingsDriver($config, $httpClient, $this->events),
        default => throw new InvalidArgumentException("Unknown client: {$config->providerType}"),
    };
}
```



## Adding Custom Middleware

You can extend Polyglot's HTTP layer by creating custom middleware:

```php
namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class YourCustomMiddleware extends BaseMiddleware {
    protected function beforeRequest(HttpClientRequest $request): void {
        // Modify the request before it's sent
    }

    protected function afterRequest(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        // Modify the response after it's received
        return $response;
    }
}
```

Then, add your middleware to the HTTP client:

```php
$httpClient = new HttpClient();
$httpClient->withMiddleware(new YourCustomMiddleware());

$inference = new Inference();
$inference->withHttpClient($httpClient);
```
