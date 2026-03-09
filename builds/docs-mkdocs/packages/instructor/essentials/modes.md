---
title: Modes
description: 'Choose how Instructor asks the model for structured output.'
---

Output mode is a runtime concern.

## Default

`OutputMode::Tools` is the default and the recommended starting point.

```php
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
)->withOutputMode(OutputMode::Tools);
// @doctest id="9e89"
```

## Other Modes

- `Json`
- `JsonSchema`
- `MdJson`

Use them when a provider or workflow needs a JSON-first response instead of tool calling.
