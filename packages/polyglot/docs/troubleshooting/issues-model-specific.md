---
title: Model-Specific Issues
description: Diagnose and resolve issues caused by differences in model capabilities.
---

Different LLM models have different capabilities and limitations, even within the same provider. A request that works perfectly with one model may fail or produce unexpected results with another. Understanding these differences is key to building reliable applications.

## Symptoms

- Errors like "model not found," "parameter not supported," or "context length exceeded"
- Unexpected or degraded response quality from certain models
- Requests that succeed on one model but fail on another
- Tool calls or JSON output that work with some models but not others

## Check Model Availability

Verify that the model identifier in your preset or request matches a model that is currently available from the provider. Model names are case-sensitive and must be exact:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

// Correct: exact model identifier
$text = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages(Messages::fromString('Hello'))
    ->get();
```

Models are periodically deprecated or renamed by providers. If a model that previously worked suddenly fails, check the provider's release notes for changes.

## Context Length Limits

Each model has a maximum context length (measured in tokens). If your input exceeds this limit, the provider returns an error. The `contextLength` field in the preset defines this limit for reference, but the actual enforcement happens at the provider.

Common context windows:

| Model | Approximate Context Window |
|---|---|
| GPT-4.1 | 1,000,000 tokens |
| GPT-4.1-nano | 1,000,000 tokens |
| Claude Haiku 4.5 | 200,000 tokens |
| Gemini models | varies by model |
| Llama 3 (via Ollama) | 128,000 tokens |

When you hit context limits, consider:

- Summarizing or truncating the input
- Splitting the request into smaller chunks
- Switching to a model with a larger context window

## Tool and Function Calling Support

Not all models support tool (function) calling. If you pass `tools` to a model that does not support them, the provider may return an error or silently ignore the tools.

When debugging tool-related failures, first confirm the request works without tools:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

// Step 1: Test plain text output
$text = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages(Messages::fromString('What is 2 + 2?'))
    ->get();

echo $text; // Verify this works first

// Step 2: Then add tools back
$text = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages(Messages::fromString('What is 2 + 2?'))
    ->withTools($myTools)
    ->get();
```

## JSON and Structured Output Support

Models vary in their support for structured output formats:

- **JSON Schema mode** -- the model is constrained to output JSON matching a specific schema. Only some models support this.
- **JSON object mode** -- the model is instructed to output valid JSON, but without schema enforcement.
- **Plain text** -- all models support this.

If JSON schema output fails, try JSON object mode or plain text as a fallback. Polyglot's `responseFormat` option controls this, but the actual behavior depends on the model.

## Streaming Support

Most modern models support streaming, but some do not. If enabling streaming causes errors, test with a non-streaming request first:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

// Test non-streaming first
$text = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages(Messages::fromString('Write a haiku.'))
    ->get();

// Then test streaming
$stream = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages(Messages::fromString('Write a haiku.'))
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
```

## Vision and Multimodal Capabilities

Only certain models support image inputs. Sending images to a text-only model will cause an error. Check the provider's documentation to confirm which models accept multimodal input.

## Implement Model Fallbacks

For production applications, implement a fallback strategy that tries alternative models when the preferred model fails:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

function withFallback(array $models, string $prompt): string {
    $lastException = null;

    foreach ($models as $model) {
        try {
            return Inference::using('openai')
                ->withModel($model)
                ->withMessages(Messages::fromString($prompt))
                ->get();
        } catch (\Exception $e) {
            $lastException = $e;
            // Log the failure and try the next model
        }
    }

    throw new \RuntimeException(
        "All models failed. Last error: " . $lastException?->getMessage(),
        previous: $lastException,
    );
}

// Try capable models first, then fall back to simpler ones
$response = withFallback(
    ['gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano'],
    'Explain general relativity.',
);
```

## Debugging Approach

When a model-specific issue arises, use this systematic approach:

1. **Reduce to plain text.** Remove tools, response format, and streaming. If the plain text request fails, the problem is not model-capability related (check authentication, configuration, or connection).
2. **Add features one at a time.** Re-enable streaming, then response format, then tools. The first feature that causes failure identifies the unsupported capability.
3. **Check the provider's model documentation.** Verify that the specific model version supports the feature you need.
4. **Try a different model** from the same provider to confirm whether the issue is model-specific or provider-wide.
