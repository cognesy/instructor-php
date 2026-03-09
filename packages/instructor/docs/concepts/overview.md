---
title: Overview
description: 'The small set of concepts behind structured output.'
---

Instructor keeps the model simple.

## Request

A request is the combination of input plus a response model, with optional system text, prompt text, examples, model, and provider options.

## Response Model

The response model defines the shape you want back.

Common choices:

- a PHP class
- a JSON schema array
- `Scalar`, `Sequence`, or `Maybe`

## Runtime

`StructuredOutputRuntime` owns provider setup and runtime behavior such as retries, output mode, events, and pipeline overrides.

## Execution

`StructuredOutput` builds the request. `PendingStructuredOutput` executes it lazily. `StructuredOutputStream` handles streaming.
