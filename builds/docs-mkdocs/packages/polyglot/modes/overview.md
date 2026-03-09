---
title: Response Shapes
description: Shape responses with explicit request fields.
---

Polyglot 2.0 does not use output mode enums.

Instead, response shape is controlled by the same request fields providers already expect:

- plain text: default behavior
- JSON object: `responseFormat`
- JSON schema: `responseFormat`
- tool calls: `tools` and `toolChoice`

That keeps Polyglot close to the underlying APIs and makes requests easier to reason about.
