---
title: Working with Embeddings
description: 'Learn how to work with vector embeddings in Polyglot.'
---


## The Embeddings Class

Polyglot provides the `Embeddings` class as the primary interface for generating and working with vector embeddings.

### Creating an Embeddings Instance

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

// Create a basic embeddings instance with default settings
$embeddings = new Embeddings();

// Create an embeddings instance with a specific connection
$embeddings = new Embeddings('openai');

// Alternative method to specify connection
$embeddings = (new Embeddings())->using('openai');
```

### Key Methods

The `Embeddings` class provides several important methods:

- `create()`: Generates embeddings for input text
- `using()`: Specifies which connection preset to use
- `withConfig()`: Sets a custom configuration
- `withHttpClient()`: Specifies a custom HTTP client
- `withModel()`: Overrides the default model
- `findSimilar()`: Finds documents similar to a query




## Generating Embeddings

The core functionality of the `Embeddings` class is to transform text into vector representations.

### Basic Embedding Generation

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings();
$result = $embeddings->with('The quick brown fox jumps over the lazy dog.')->get();

// Get the vector values from the first (and only) result
$vector = $result->first()?->values();

echo "Generated a vector with " . count($vector) . " dimensions.\n";
```

### Embedding Multiple Texts

You can generate embeddings for multiple texts in a single request, which is more efficient than making separate requests:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings();

$documents = [
    "The quick brown fox jumps over the lazy dog.",
    "Machine learning models can process text into vector representations.",
    "Embeddings capture semantic relationships between words and documents."
];

$result = $embeddings->with($documents)->get();

// Get all vectors
$vectors = $result->vectors();

foreach ($vectors as $index => $vector) {
    echo "Document " . ($index + 1) . " has a vector with " . count($vector->values()) . " dimensions.\n";
}
```

### Accessing Embedding Results

The `create()` method returns an `EmbeddingsResponse` object with several useful methods:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings();
$result = $embeddings->with('Sample text for embedding')->get();

// Get the first vector
$firstVector = $result->first();

// Get the last vector (useful when processing multiple inputs)
$lastVector = $result->last();

// Get all vectors
$allVectors = $result->vectors();

// Get all vector values as a simple array of arrays
$valuesArray = $result->toValuesArray();

// Get usage information
$usage = $result->usage();
echo "Input tokens: " . $usage->input() . "\n";
echo "Output tokens: " . $usage->output() . "\n";
echo "Total tokens: " . $usage->total() . "\n";
```

### Working with Vector Objects

Each vector in the response is represented by a `Vector` object with its own methods:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings();
$result = $embeddings->with('Sample text for embedding')->get();
$vector = $result->first();

// Get vector values
$values = $vector->values();

// Get vector ID (index)
$id = $vector->id();

// Compare with another vector
$otherVector = $result->with('Another text for comparison')->first();
$similarity = $vector->compareTo($otherVector, 'cosine');
```



## Working with Different Providers

Polyglot supports multiple embedding providers, each with their own strengths and characteristics.

### Switching Between Providers

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

// Compare embeddings from different providers
$text = "Artificial intelligence is transforming industries worldwide.";

// OpenAI embeddings
$openaiEmbeddings = new Embeddings('openai');
$openaiResult = $openaiEmbeddings->with($text)->get();
echo "OpenAI embedding dimensions: " . count($openaiResult->first()?->values()) . "\n";

// Cohere embeddings
$cohereEmbeddings = new Embeddings('cohere1');
$cohereResult = $cohereEmbeddings->with($text)->get();
echo "Cohere embedding dimensions: " . count($cohereResult->first()?->values()) . "\n";

// Mistral embeddings
$mistralEmbeddings = new Embeddings('mistral');
$mistralResult = $mistralEmbeddings->with($text)->get();
echo "Mistral embedding dimensions: " . count($mistralResult->first()?->values()) . "\n";
```

### Provider-Specific Options

Different providers may support additional options for embedding generation:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

// Example with OpenAI-specific options
$openaiEmbeddings = new Embeddings('openai');
$response = $openaiEmbeddings->with(
    input: ["Sample text for embedding"],
    options: [
        'encoding_format' => 'float',  // Get float values instead of base64
        'dimensions' => 512,           // Request a specific vector size (if supported)
    ]
)->get();

// Example with Cohere-specific options
$cohereEmbeddings = new Embeddings('cohere1');
$response = $cohereEmbeddings->with(
    input: ["Sample text for embedding"],
    options: [
        'input_type' => 'classification',  // Cohere-specific option
        'truncate' => 'END',               // How to handle texts that exceed the token limit
    ]
)->get();
```

### Models and Dimensions

Different embedding models produce vectors of different dimensions:

```php
<?php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;use Cognesy\Polyglot\Embeddings\Embeddings;

// Create custom configuration with a specific model
$config = new EmbeddingsConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: getenv('OPENAI_API_KEY'),
    endpoint: '/embeddings',
    model: 'text-embedding-3-large',  // Use the larger model
    dimensions: 3072,                 // Specify expected dimensions
);

$embeddings = new Embeddings();
$embeddings->withConfig($config);

$response = $embeddings->with("Test text for large embedding model")->get();
echo "Vector dimensions: " . count($response->first()?->values()) . "\n";
```

