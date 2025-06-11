---
title: Text Mode
description: 'Learn how to use text mode in Polyglot for LLM responses.'
---


Text mode is the default and simplest output format, returning unstructured text from the model.

### Basic Text Generation

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$inference = new Inference();

// OutputMode::Text is the default, so you don't need to specify it
$response = $inference
    ->with(
        messages: 'What is the capital of France?',
        mode: OutputMode::Text  // Optional, this is the default
    )
    ->get();

echo "Response: $response\n";
// Output: Response: The capital of France is Paris.
```

### When to Use Text Mode

Text mode is ideal for:
- Simple question answering
- Creative content generation
- Conversational interactions
- Summaries and paraphrasing
- Any use case where structured data is not required

### Text Mode Across Providers

Text mode works consistently across all providers, making it the most portable option:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();

// Using OpenAI
$openAIResponse = $inference
    ->using('openai')
    ->withMessages('Write a short poem about the ocean.')
    ->get();

echo "OpenAI response:\n$openAIResponse\n\n";

// Using Anthropic
$anthropicResponse = $inference
    ->using('anthropic')
    ->with('Write a short poem about the ocean.')
    ->get();

echo "Anthropic response:\n$anthropicResponse\n\n";

// Using any other provider
// ...
```
