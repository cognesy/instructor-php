---
title: Request/Response Lifecycle
description: 'Learn about the internal request/response lifecycle in Polyglot.'
---

Let's follow the complete flow of a request through Polyglot:

## Request Processing

1. Application creates an `Inference` object
2. Application calls `create()` with parameters
3. `Inference` creates an `InferenceRequest`.
4. `Inference` creates a `PendingInference` object with the instances of request, driver and event dispatcher.
5. `Inference` returns a `PendingInference` object to the application.

## Response Processing

1. Application accesses the `PendingInference` object content, e.g. via `response()` method.
2. `PendingInference` checks if HTTP request has been already executed.
   - If already sent, it returns the cached response.
3. `PendingInference` dispatches the `InferenceRequested` event
3. `PendingInference` passes the request to the driver.
4. Driver uses request adapter to create HTTP request
5. Request adapter uses request body formatter and message formatter.
8. Driver sends the HTTP request and returns it to `PendingInference`.
5. `PendingInference` calls the driver to read and parse the response.
3. Driver uses a response adapter to extract content into appropriate fields of `InferenceResponse` object
4. `PendingInference` dispatches the `InferenceResponseReceived` event
5. Result `InferenceResponse` object is returned to the application
