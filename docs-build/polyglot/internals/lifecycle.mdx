---
title: Request/Response Lifecycle
description: 'Learn about the internal request/response lifecycle in Polyglot.'
---

Let's follow the complete flow of a request through Polyglot:

## Request Processing

1. Application creates an `Inference` object
2. Application calls `create()` with parameters
3. `Inference` creates an `InferenceRequest`.
4. `Inference` dispatches the `InferenceRequested` event
5. `Inference` passes the request to the driver
6. Driver uses request adapter to create HTTP request
7. Request adapter uses body formatter and message formatter
8. Driver sends the HTTP request
4. `Inference` returns an `InferenceResponse` object.

## Response Processing

1. Application accesses the `InferenceResponse` object content, e.g. via `response()` method.
2. `InferenceResponse` calls the driver to read and parse the response.
3. Driver uses a response adapter to extract content into appropriate fields of `LLMResponse` object
4. `InferenceResponse` dispatches the `LLMResponseReceived` event
5. Result `LLMResponse` object is returned to the application
