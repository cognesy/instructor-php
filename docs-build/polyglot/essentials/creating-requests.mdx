---
title: Creating Requests
description: 'Learn how to create requests to LLM providers using Polyglot.'
---

This section covers how to create requests to LLM providers using the Polyglot library. It includes examples of basic requests, handling multiple messages, and using different message formats.


## Creating Requests

The `create()` method is the main way to send requests to LLM providers. It accepts several parameters:

```php
public function create(
    string|array $messages = [],    // The messages to send to the LLM
    string $model = '',             // The model to use (overrides default)
    array $tools = [],              // Tools/functions for the model to use
    string|array $toolChoice = [],  // Tool selection preference
    array $responseFormat = [],     // Response format specification
    array $options = [],            // Additional request options
    Mode $mode = OutputMode::Text         // Output mode (Text, JSON, etc.)
): InferenceResponse
```



## Basic Request Example

Here's a simple example of creating a request:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

$inference = new Inference();
$response = $inference->create(
    messages: 'What is the capital of France?'
)->toText();

echo "Response: $response";
```


## Request with Multiple Messages

For chat-based interactions, you can pass an array of messages:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

$inference = new Inference();
$response = $inference->create(
    messages: [
        ['role' => 'system', 'content' => 'You are a helpful assistant who provides concise answers.'],
        ['role' => 'user', 'content' => 'What is the capital of France?'],
        ['role' => 'assistant', 'content' => 'Paris.'],
        ['role' => 'user', 'content' => 'And what about Germany?']
    ]
)->toText();

echo "Response: $response";
```


## Message Formats

Polyglot supports different message formats depending on the provider:

- **String**: A simple string will be converted to a user message
- **Array of messages**: Each message should have a `role` and `content` field
- **Multimodal content**: Some providers support images in messages

Example with image (for providers that support it):

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

$imageData = base64_encode(file_get_contents('image.jpg'));
$messages = [
    [
        'role' => 'user',
        'content' => [
            [
                'type' => 'text',
                'text' => 'What's in this image?'
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:image/jpeg;base64,$imageData"
                ]
            ]
        ]
    ]
];

$inference = new Inference()->withConnection('openai');
$response = $inference->create(messages: $messages)->toText();
```
