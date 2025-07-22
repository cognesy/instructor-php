---
title: Overview of Embeddings
description: 'Embeddings are a way to represent data (text, images, audio) in a continuous vector space.'
---

Embeddings are a key component of many LLM-based solutions and are used to represent text
(or multimodal data) with numbers capturing their meaning and relationships.

Embeddings are numerical representations of text or other data that capture semantic meaning in a way that computers can process efficiently. They enable powerful applications like semantic search, document clustering, recommendation systems, and more. This chapter explores how to use Polyglot's Embeddings API to work with vector embeddings across multiple providers.


## Understanding Embeddings

Before diving into code, it's helpful to understand what embeddings are and how they work:

- Embeddings represent words, phrases, or documents as vectors of floating-point numbers in a high-dimensional space
- Similar items (semantically related) have vectors that are closer together in this space
- The "distance" between vectors can be measured using metrics like cosine similarity or Euclidean distance
- Modern embedding models are trained on massive corpora of text to capture nuanced relationships

Common use cases for embeddings include:

- **Semantic search**: Finding documents similar to a query based on meaning, not just keywords
- **Clustering**: Grouping similar documents together
- **Classification**: Assigning categories to documents based on their content
- **Recommendations**: Suggesting related items
- **Information retrieval**: Finding relevant information in large datasets



## `Embeddings` class

The `Embeddings` class is a facade that provides access to embeddings APIs across multiple providers.
It combines functionality through traits for provider configuration, request building, and result handling.

### Architecture Overview

The `Embeddings` class combines functionality through traits:
- **HandlesInitMethods**: Provider configuration and setup
- **HandlesFluentMethods**: Request parameter configuration
- **HandlesInvocation**: Request execution and PendingEmbeddings creation
- **HandlesShortcuts**: Convenient methods for common result formats


## Supported providers

`Embeddings` class supports the following embeddings providers:
- **Azure OpenAI**: Azure-hosted OpenAI embedding models
- **Cohere**: Cohere's embedding models
- **Gemini**: Google's Gemini embedding models  
- **Jina**: Jina AI's embedding models
- **OpenAI**: OpenAI's embedding models (text-embedding-ada-002, text-embedding-3-small, text-embedding-3-large)

Provider configurations are managed through the configuration system.


## Basic Usage

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

// Simple embedding generation
$embeddings = new Embeddings();
$result = $embeddings->with('The quick brown fox jumps over the lazy dog.')->get();

// Get the vector values from the first result
$vector = $result->first()->values();
echo "Generated a vector with " . count($vector) . " dimensions.\n";
```


## Provider Configuration Methods

Configure the underlying embeddings provider:

```php
// Provider selection and configuration
$embeddings->using('openai');                          // Use preset configuration
$embeddings->withPreset('openai');                     // Alternative preset method
$embeddings->withDsn('openai://model=text-embedding-3-large'); // Configure via DSN
$embeddings->withConfig($customConfig);                // Explicit configuration
$embeddings->withConfigProvider($configProvider);     // Custom config provider

// HTTP and debugging
$embeddings->withHttpClient($customHttpClient);       // Custom HTTP client
$embeddings->withDebugPreset('verbose');              // Debug configuration

// Driver management
$embeddings->withDriver($customDriver);               // Custom vectorization driver
$embeddings->withProvider($customProvider);           // Custom provider instance
```


## Request Configuration Methods

Configure the embedding request:

```php
// Input configuration
$embeddings->withInputs('Single text input');         // Single string
$embeddings->withInputs(['Text 1', 'Text 2']);       // Multiple strings
$embeddings->with('Input text');                      // Shorthand input method

// Model and options
$embeddings->withModel('text-embedding-3-large');    // Specific model
$embeddings->withOptions(['dimensions' => 1536]);     // Provider-specific options

// Complete configuration
$embeddings->with(
    input: ['Text 1', 'Text 2'],
    options: ['dimensions' => 1536],
    model: 'text-embedding-3-large'
);
```


## Response Methods

Get embeddings in different formats:

```php
// Full response object
$response = $embeddings->get();                       // EmbeddingsResponse object

// Vector extraction
$vectors = $embeddings->vectors();                    // Array of Vector objects
$firstVector = $embeddings->first();                 // First Vector object
$values = $embeddings->first()->values();           // Array of floats

// Advanced response handling
$pending = $embeddings->create();                    // PendingEmbeddings for custom handling
$response = $pending->get();                         // Execute and get response
```


## Working with Multiple Providers

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

// OpenAI embeddings
$openaiVectors = (new Embeddings())
    ->using('openai')
    ->withModel('text-embedding-3-large')
    ->with(['Document 1', 'Document 2'])
    ->vectors();

// Cohere embeddings  
$cohereVectors = (new Embeddings())
    ->using('cohere')
    ->withModel('embed-english-v3.0')
    ->with(['Document 1', 'Document 2'])
    ->vectors();

echo "OpenAI dimensions: " . count($openaiVectors[0]->values()) . "\n";
echo "Cohere dimensions: " . count($cohereVectors[0]->values()) . "\n";
```


## Custom Configuration

Create custom configurations for specific use cases:

```php
<?php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;

// Create custom configuration
$config = new EmbeddingsConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: getenv('OPENAI_API_KEY'),
    endpoint: '/embeddings',
    model: 'text-embedding-3-large',
    dimensions: 3072,
    maxInputs: 100,
    driver: 'openai'
);

// Use custom configuration
$embeddings = (new Embeddings())
    ->withConfig($config)
    ->with('Custom configuration example');

$vector = $embeddings->first()->values();
echo "Generated embedding with " . count($vector) . " dimensions\n";
```


## Driver Registration

Register custom drivers for new providers:

```php
// Register with class name
Embeddings::registerDriver('custom-provider', CustomEmbeddingsDriver::class);

// Register with factory callable
Embeddings::registerDriver('custom-provider', function($config, $httpClient) {
    return new CustomEmbeddingsDriver($config, $httpClient);
});
```
