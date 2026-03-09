---
title: Lifecycle
description: 'What happens between request creation and the final result.'
---

The structured-output lifecycle is straightforward:

1. Build a `StructuredOutputRequest`
2. Execute through `PendingStructuredOutput`
3. Get a raw inference response
4. Extract structured data from the response
5. Deserialize it into the target shape
6. Validate it
7. Return a value, response object, or stream updates

Retries happen inside that flow when runtime configuration allows them.
