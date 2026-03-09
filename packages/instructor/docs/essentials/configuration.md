---
title: Configuration
description: 'Keep provider, runtime, and request configuration separate.'
---

There are three useful layers of configuration.

## Provider

Use `LLMConfig` to choose the provider and model baseline.

## Runtime

Use `StructuredOutputRuntime` for behavior shared across requests:

- retries
- output mode
- events
- validators
- transformers
- deserializers
- extractors

## Request

Use `StructuredOutput` for one request:

- messages
- response model
- system text
- prompt text
- examples
- per-call model and options

This split keeps most applications simple: one runtime, many small requests.
