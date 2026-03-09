---
title: Function Calls
description: 'How tool-calling fits into structured output.'
---

Instructor uses tool-calling internally when the runtime is in `OutputMode::Tools`, which is the default.

For most applications, this is an implementation detail. You define a response model and read the result.

Change the mode only when a provider or workflow needs JSON output instead of tool-calling.
