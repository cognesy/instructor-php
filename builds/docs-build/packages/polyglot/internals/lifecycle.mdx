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
5. `PendingInference` creates an internal `InferenceExecutionSession` to own the raw mutable lifecycle.
6. `Inference` returns a `PendingInference` object to the application.

## Response Processing

1. Application accesses the `PendingInference` object content, e.g. via `response()` method.
2. `PendingInference` delegates raw lifecycle work to `InferenceExecutionSession`.
3. `InferenceExecutionSession` checks if HTTP request has been already executed.
   - If already sent, it returns the cached response.
4. `InferenceExecutionSession` dispatches the `InferenceStarted` event.
5. `InferenceExecutionSession` dispatches the `InferenceAttemptStarted` event.
6. Driver dispatches the `InferenceRequested` event and sends the HTTP request.
7. Driver parses provider response into `InferenceResponse`.
8. Driver dispatches the `InferenceResponseCreated` event.
9. `InferenceExecutionSession` dispatches `InferenceAttemptSucceeded`, `InferenceUsageReported`, and `InferenceCompleted`.
10. Result `InferenceResponse` object is returned to the application.

`PendingInference` remains the public lazy handle. `InferenceExecutionSession` owns the mutable raw lifecycle behind it. Higher layers may wrap `PendingInference`, but they should not duplicate this whole flow unless they are adding a distinct higher-level completion contract.
