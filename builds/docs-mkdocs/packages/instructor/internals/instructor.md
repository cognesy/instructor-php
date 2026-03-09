---
title: Instructor
description: 'The main public types in the package.'
---

The public surface of this package is intentionally small.

- `StructuredOutput` is the main facade
- `StructuredOutputRuntime` configures provider and runtime behavior
- `PendingStructuredOutput` is the lazy execution handle returned by `create()`
- `StructuredOutputStream` exposes streaming reads
- `StructuredOutputResponse` wraps the parsed value and raw provider response

Most users should stay at this level.
