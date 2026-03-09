---
title: Settings Class
description: 'There is no single global settings object in this package.'
---

Configuration is split on purpose:

- `LLMConfig` for provider setup
- `StructuredOutputConfig` for structured-output behavior
- `StructuredOutputRuntime` for assembled runtime state

That keeps request configuration local and shared behavior reusable.
