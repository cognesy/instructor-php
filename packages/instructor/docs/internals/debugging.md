---
title: Debugging
description: 'The fastest ways to inspect what happened.'
---

When a request does not behave as expected, start with the smallest useful inspection point.

- `response()` for parsed value plus raw response
- `rawResponse()` for provider output
- `execution()` on `PendingStructuredOutput` for execution metadata
- `responses()` on `StructuredOutputStream` for streaming snapshots
- runtime events for deeper tracing

In most cases, `response()` or a runtime wiretap is enough.
