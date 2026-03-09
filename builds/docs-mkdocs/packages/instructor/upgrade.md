---
title: Upgrade
description: 'What to expect in the 2.0-era API.'
---

The current docs are written for the refactored structured-output API.

## The Main Shape

The core flow is now:

- `StructuredOutput` for request construction
- `StructuredOutputRuntime` for runtime behavior
- `PendingStructuredOutput` for lazy execution
- `StructuredOutputStream` for streaming reads

## Important Differences From Older Docs

- configuration belongs on the runtime, not on a global instructor object
- `create()` returns a lazy handle
- `stream()` returns a dedicated stream object
- `StructuredOutput::fromConfig(...)` and `StructuredOutput::using(...)` are valid entry points
- published config files are optional, not required for normal usage

If you are updating older examples, start by rewriting them around `StructuredOutput->with(...)->get()`.
