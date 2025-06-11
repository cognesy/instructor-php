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

`Embeddings` class offers access to embeddings APIs and convenient methods to find top K vectors
or documents most similar to provided query.



## Supported providers

`Embeddings` class supports following embeddings providers:
- Azure
- Cohere
- Gemini
- Jina
- Mistral
- OpenAI

Embeddings providers access details can be found and modified via
`/config/embed.php`.


## Working with embeddings

Polyglot also makes it easy to generate embeddings:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

// Generate embeddings for a document
$embeddings = new Embeddings();
$result = $embeddings->with('The quick brown fox jumps over the lazy dog.')->get();

// Get the vector values from the first (and only) result
$vector = $result->first()->values();

echo "Generated a vector with " . count($vector) . " dimensions.\n";
```


## Selecting a provider

In this example, we use OpenAI embeddings provider to generate embeddings for a given list of documents
(only one in this case).

The result is a list of embeddings vectors (one per document).

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$docs = ['Computer vision models are used to analyze images and videos.'];

$embedding = (new Embeddings)
    ->using('openai')
    ->with(input: $docs)
    ->vectors();
?>
```


## Specifying Models for Embeddings

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings('openai');

// Use the default model
$defaultVector = $embeddings->with("Sample text")->first()->values();

// Use a specific model
$largeVector = $embeddings->withModel('text-embedding-3-large')
    ->with("Sample text")
    ->first()
    ->values();

echo "Default model dimensions: " . count($defaultVector) . "\n";
echo "Large model dimensions: " . count($largeVector) . "\n";
```


## Creating Custom Configuration

You can create a custom configuration for your embeddings provider, allowing you to specify different models, dimensions, and other parameters:

```php
<?php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;use Cognesy\Polyglot\Embeddings\Embeddings;

// Create a custom embeddings configuration
$embeddingsConfig = new EmbeddingsConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: getenv('OPENAI_API_KEY'),
    endpoint: '/embeddings',
    model: 'text-embedding-3-large',
    dimensions: 3072,
    maxInputs: 100,
    driver: 'openai'
);

// Use the custom configuration
$embeddings = new Embeddings();
$embeddings->withConfig($embeddingsConfig);

$vector = $embeddings->with('Custom configuration example')
    ->first()
    ->values();

echo "Generated embedding with " . count($vector) . " dimensions\n";
```
