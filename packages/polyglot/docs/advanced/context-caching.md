---
title: Context Caching
description: 'How to use context caching in Polyglot'
---

Context caching improves performance by reusing parts of a conversation, reducing token usage and API costs. This is particularly useful for multi-turn conversations or when processing large documents.


## Using Cached Context

Polyglot supports context caching through the `withCachedContext()` method:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Create an inference object
$inference = new Inference()->using('anthropic');

// Set up a conversation with cached context
$inference->withCachedContext(
    messages: [
        ['role' => 'system', 'content' => 'You are a helpful assistant who provides concise answers.'],
        ['role' => 'user', 'content' => 'I want to discuss machine learning concepts.'],
        ['role' => 'assistant', 'content' => 'Great! I\'d be happy to discuss machine learning concepts with you. What specific aspect would you like to explore?'],
    ]
);

// First query using the cached context
$response1 = $inference->with(
    messages: 'What is supervised learning?'
)->response();

echo "Response 1: " . $response1->content() . "\n";
echo "Tokens from cache: " . $response1->usage()->cacheReadTokens . "\n\n";

// Second query, still using the same cached context
$response2 = $inference->with(
    messages: 'And what about unsupervised learning?'
)->response();

echo "Response 2: " . $response2->content() . "\n";
echo "Tokens from cache: " . $response2->usage()->cacheReadTokens . "\n";
```

### Provider Support for Context Caching

Different providers have varying levels of support for context caching:

- **Anthropic**: Supports native context caching with explicit cache markers
- **OpenAI**: Provides automatic caching for optimization, but not as explicit as Anthropic
- **Other providers**: May not support native caching, but Polyglot still helps manage conversation state



## Processing Large Documents with Cached Context

Context caching is particularly valuable when working with large documents:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Load a large document
$documentContent = file_get_contents('large_document.txt');

// Set up cached context with the document
$inference = new Inference()->using('anthropic');
$inference->withCachedContext(
    messages: [
        ['role' => 'system', 'content' => 'You will help analyze and summarize documents.'],
        ['role' => 'user', 'content' => 'Here is the document to analyze:'],
        ['role' => 'user', 'content' => $documentContent],
    ]
);

// Ask multiple questions about the document without resending it each time
$questions = [
    'Summarize the key points of this document in 3 bullets.',
    'What are the main arguments presented?',
    'Are there any contradictions or inconsistencies in the text?',
    'What conclusions can be drawn from this document?',
];

foreach ($questions as $index => $question) {
    $response = $inference->with(messages: $question)->response();

    echo "Question " . ($index + 1) . ": $question\n";
    echo "Answer: " . $response->content() . "\n";
    echo "Tokens from cache: " . $response->usage()->cacheReadTokens . "\n\n";
}
```
