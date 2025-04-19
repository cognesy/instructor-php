---
title: Request Options
description: 'Learn how to customize request options for LLM inference in Polyglot.'
---

The `options` parameter allows you to customize various aspects of the request.

> NOTE: Except for `max_tokens`, all option parameters are provider-specific and may not be available or compatible with all providers.
> Check the provider's API documentation for details.


## Common Options

```php
$options = [
    // Generation parameters
    'temperature' => 0.7,         // Controls randomness (0.0 to 1.0)
    'max_tokens' => 1000,         // Maximum tokens to generate
    'top_p' => 0.95,              // Nucleus sampling parameter
    'frequency_penalty' => 0.0,   // Penalize repeated tokens
    'presence_penalty' => 0.0,    // Penalize repeated topics
    'stream' => false,            // Enable streaming responses
    'stop' => ["\n\n", "User:"],  // Stop sequences

    // Provider-specific options
    'top_k' => 40,                // For some providers
    'response_format' => [        // OpenAI-specific format control
        'type' => 'json_object'
    ],
    // Additional provider-specific options...
];

$inference = new Inference();
$response = $inference->create(
    messages: 'Write a short poem about programming.',
    options: $options
)->toText();
```



## Provider-Specific Options

Different providers may support additional options. Consult the provider's documentation for details:

```php
// Anthropic-specific options
$anthropicOptions = [
    'temperature' => 0.7,
    'max_tokens' => 1000,
    'top_p' => 0.9,
    'stop_sequences' => ["\n\nHuman:"],
    'stream' => true,
];

$inference = new Inference()->withConnection('anthropic');
$response = $inference->create(
    messages: 'Write a short poem about programming.',
    options: $anthropicOptions
)->toText();
```
