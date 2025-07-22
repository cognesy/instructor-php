---
title: Creating Requests
description: 'Learn how to create requests to LLM providers using Polyglot.'
meta:
  - name: 'has_code'
    content: true
---

This section covers how to create requests to LLM providers using the Polyglot library. It includes examples of basic requests, handling multiple messages, and using different message formats.


## Creating Requests

The `with()` method is the main way to set the parameters of the requests to LLM providers.

It accepts several parameters:

```php
public function with(
    string|array $messages = [],    // The messages to send to the LLM
    string $model = '',             // The model to use (overrides default)
    array $tools = [],              // Tools/functions for the model to use
    string|array $toolChoice = [],  // Tool selection preference
    array $responseFormat = [],     // Response format specification
    array $options = [],            // Additional request options
    Mode $mode = OutputMode::Text   // Output mode (Text, JSON, etc.)
) : self
```


## Basic Request Example

Here's a simple example of creating a request:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference
    ->with(
        messages: 'What is the capital of France?'
    )
    ->create() // create pending inference
    ->get();   // get the data - here it executes the request

echo "Response: $response";
```


## Request with Multiple Messages

For chat-based interactions, you can pass an array of messages:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference
    ->withMessages([
        ['role' => 'system', 'content' => 'You are a helpful assistant who provides concise answers.'],
        ['role' => 'user', 'content' => 'What is the capital of France?'],
        ['role' => 'assistant', 'content' => 'Paris.'],
        ['role' => 'user', 'content' => 'And what about Germany?']
    ])
    ->get();

echo "Response: $response";
```

## Using `Messages` Class

You can also use the `Messages` class to create message sequences more conveniently:

```php
<?php
use Cognesy\Messages\Messages;
use Cognesy\Messages\Utils\Image;
use Cognesy\Polyglot\Inference\Inference;

$messages = (new Messages)
    ->asSystem('You are a senior PHP8 backend developer.')
    ->asDeveloper('Be concise and use modern PHP8.2+ features.') // OpenAI developer role is supported and normalized for other providers
    ->asUser([
        'What is the best way to handle errors in PHP8?',
        'Provide a code example.',
    ]); // you can pass array of strings to create multiple content parts

$response = (new Inference)
    ->using('openai')
    ->withModel('gpt-4o')
    ->withMessages($messages)
    ->get();
```

## Message Formats

Polyglot supports different message formats depending on the provider:

- **String**: A simple string will be converted to a user message
- **Array of messages**: Each message should have a `role` and `content` field
- **Multimodal content**: Some providers support images in messages

Example with image (for providers that support it):

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$imageData = base64_encode(file_get_contents('image.jpg'));
$messages = [
    [
        'role' => 'user',
        'content' => [
            [
                'type' => 'text',
                'text' => 'What\'s in this image?'
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

$response = (new Inference())
    ->using('openai')
    ->withModel('gpt-4o') // use multimodal model
    ->with(messages: $messages)
    ->get();
```

Instructor library offers `Cognesy\Messages\Utils\Image` class for easier conversion of image files to the message format.
