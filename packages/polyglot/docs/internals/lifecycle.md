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
3. `PendingInference` dispatches the `InferenceStarted` event.
4. `PendingInference` dispatches the `InferenceAttemptStarted` event.
5. Driver dispatches the `InferenceRequested` event and sends the HTTP request.
6. Driver parses provider response into `InferenceResponse`.
7. Driver dispatches the `InferenceResponseCreated` event.
8. `PendingInference` dispatches `InferenceAttemptSucceeded`, `InferenceUsageReported`, and `InferenceCompleted`.
9. Result `InferenceResponse` object is returned to the application.
