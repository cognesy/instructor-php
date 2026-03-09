---
title: JSON Extraction
description: 'How Instructor recovers structured data from model output.'
---

Instructor includes response extractors that pull JSON or tool arguments out of the model response before deserialization.

That recovery step is built in. In normal usage, you do not configure it directly.

If you need custom extraction logic, add extractors on `StructuredOutputRuntime` with `withExtractors(...)`.
