---
title: Overview
description: 'Instructor HTTP client layer - framework agnostic, middleware support, and streaming capabilities.'
---

## Purpose and Goals

The Instructor HTTP client API is designed to provide a consistent interface for making HTTP requests across different PHP environments.

It provides a single API regardless of the underlying HTTP client available in the given environment, which may be Symfony, Laravel, Slim, or just vanilla PHP.

The primary goals of the API are:

- **Easily Switch Between HTTP Clients**: Allow developers to switch between different HTTP client libraries (like Guzzle, Symfony, and Laravel) without changing the code that makes HTTP requests
- **Framework Agnostic**: Work seamlessly in Laravel, Symfony, or any PHP application without framework-specific dependencies
- **Consistent Interface**: Provide a one way to make HTTP requests regardless of the underlying client library
- **Middleware Support**: Enable easy extension through a powerful middleware system
- **Adaptability**: Allow switching between different HTTP client implementations with minimal code changes
- **Streaming Support**: Provide first-class support for streaming responses, which is crucial for LLM interactions
- **Concurrency**: Support parallel requests through request pooling

By abstracting away the differences between various HTTP client libraries, the Instructor works across different environments and frameworks without modification.

## Key Features

### Multiple Client Support

The API supports multiple HTTP client libraries through specialized drivers:

- **Guzzle**: A popular and feature-rich HTTP client for PHP
- **Symfony HTTP Client**: The HTTP client component from the Symfony framework
- **Laravel HTTP Client**: The HTTP client built into the Laravel framework

### Middleware System

A powerful middleware architecture allows for:

- **Request Pre-processing**: Modify requests before they are sent
- **Response Post-processing**: Transform or analyze responses
- **Response Streaming**: Process streaming responses chunk by chunk
- **Debugging**: Log requests and responses for troubleshooting
- **Custom Behaviors**: Add specialized behaviors like caching or rate limiting

### Streaming Response Support

First-class support for streaming HTTP responses, which is essential for:

- **LLM Text Generation**: Process token-by-token responses from AI models
- **Large File Downloads**: Handle large files without excessive memory usage
- **Real-time Data**: Process server-sent events or other real-time data streams

### Request Pooling

Execute multiple HTTP requests concurrently for better performance:

- **Concurrent Execution**: Send multiple requests in parallel
- **Configurable Concurrency**: Control the maximum number of concurrent requests
- **Result Collection**: Process results as they arrive
- **Error Handling**: Flexible error handling strategies

### Flexible Configuration

Comprehensive configuration options:

- **Per-Client Configuration**: Different settings for each client type
- **Named Configurations**: Multiple configurations for different use cases
- **Runtime Configuration**: Change configuration during execution
- **Timeout Controls**: Fine-grained control over various timeout settings

### Debug and Testing Support

Built-in features for debugging and testing:

- **Request/Response Logging**: Detailed logging of HTTP interactions
- **Mock Client**: Test your code without making actual HTTP requests
- **Record/Replay**: Record HTTP interactions and replay them later


## Architecture Overview

The Instructor HTTP client follows a layered architecture with several key components:

### Client Layer

The `HttpClient` class serves as the main entry point and provides a fluent interface for configuring and using the HTTP client.

```
HttpClient
  └── withClient() - Switch to a different client configuration
  └── withConfig() - Apply custom configuration
  └── withDriver() - Use a custom driver
  └── withMiddleware() - Add middleware components
  └── handle() - Process an HTTP request
```

### Middleware Layer

The middleware system allows for processing requests and responses through a chain of handlers:

```
Request -> Middleware 1 -> Middleware 2 -> ... -> Driver -> External API
                                                   ↓
Response <- Middleware 1 <- Middleware 2 <- ... <- Driver <- HTTP Response
```

Key components:
- `MiddlewareStack`: Manages the collection of middleware
- `MiddlewareHandler`: Orchestrates the middleware chain execution
- `BaseMiddleware`: Base class for implementing middleware

### Driver Layer

Drivers implement the `CanHandleHttpRequest` interface and adapt different HTTP client libraries:

```
CanHandleHttpRequest (interface)
  ├── GuzzleDriver
  ├── SymfonyDriver
  ├── LaravelDriver
  └── MockHttpDriver (for testing)
```

### Adapter Layer

Response adapters convert client-specific responses to a common interface:

```
HttpClientResponse (interface)
  ├── PsrHttpResponse (Guzzle)
  ├── SymfonyHttpResponse
  ├── LaravelHttpResponse
  └── MockHttpResponse
```

## Supported HTTP Clients

### Guzzle HTTP Client

The [Guzzle HTTP Client](https://docs.guzzlephp.org/) is a powerful HTTP client library for PHP. It provides:

- PSR-7 HTTP message implementation
- Middleware system
- Request and response plugins
- HTTP/2 support (via cURL)

The `GuzzleDriver` adapts Guzzle to the Instructor HTTP client API interface.

### Symfony HTTP Client

The [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html) is a component of the Symfony framework. Features include:

- HTTP/2 push support
- PSR-18 compatibility
- Automatic content-type detection
- Proxy support

The `SymfonyDriver` adapts the Symfony HTTP Client to the Instructor HTTP client API.

### Laravel HTTP Client

The [Laravel HTTP Client](https://laravel.com/docs/http-client) is built into the Laravel framework and provides:

- Fluent, readable syntax
- Request macros
- Automatic JSON handling
- Rate limiting
- Retry logic

The `LaravelDriver` adapts the Laravel HTTP Client to the Instructor HTTP client API.

### Mock HTTP Driver

The `MockHttpDriver` provides a test double for unit testing. It doesn't make actual HTTP requests but returns predefined responses based on matching rules.
