---
title: Working with Embeddings
description: Build requests, execute them, and work with vectors and responses.
---

This page covers the full workflow of generating embeddings -- from building requests to extracting and comparing vectors.

## Generating a Single Embedding

The most common path is straightforward. Pass a text string, execute the request, and extract the vector:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs('The quick brown fox jumps over the lazy dog.')
    ->get();

$vector = $response->first()?->values() ?? [];

echo "Generated a vector with " . count($vector) . " dimensions.\n";
```

## Embedding Multiple Texts

You can generate embeddings for multiple texts in a single request, which is significantly more efficient than making separate calls:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$documents = [
    'The quick brown fox jumps over the lazy dog.',
    'Machine learning models can process text into vector representations.',
    'Embeddings capture semantic relationships between words and documents.',
];

$response = Embeddings::using('openai')
    ->withInputs($documents)
    ->get();

// Get all vectors as Vector objects
$vectors = $response->vectors();

foreach ($vectors as $index => $vector) {
    echo "Document " . ($index + 1) . ": "
        . count($vector->values()) . " dimensions\n";
}

// Or get raw float arrays directly
$valuesArray = $response->toValuesArray();
// $valuesArray[0] = [0.0123, -0.0456, ...], etc.
```

## Using the Shorthand Method

The `with()` method lets you set inputs, options, and model in a single call:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->with(
        input: ['Document one', 'Document two'],
        options: ['dimensions' => 512],
        model: 'text-embedding-3-large',
    )
    ->get();
```

## The EmbeddingsResponse Object

The `get()` method returns an `EmbeddingsResponse` with several methods for accessing results:

| Method | Returns | Description |
|---|---|---|
| `first()` | `?Vector` | The first vector, or `null` if the response is empty |
| `last()` | `?Vector` | The last vector, or `null` if the response is empty |
| `vectors()` | `Vector[]` | All vectors as an array of `Vector` objects |
| `all()` | `Vector[]` | Alias for `vectors()` |
| `toValuesArray()` | `float[][]` | All vectors as nested arrays of floats |
| `split(int $index)` | `[Vector[], Vector[]]` | Split vectors into two groups at the given index |
| `usage()` | `EmbeddingsUsage` | Token usage information for the request |

### Accessing Usage Information

Every response includes token usage data:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs('Sample text for embedding')
    ->get();

$usage = $response->usage();
echo "Input tokens: " . $usage->input() . "\n";
echo "Output tokens: " . $usage->output() . "\n";
echo "Total tokens: " . $usage->total() . "\n";
```

## Working with Vector Objects

Each embedding in the response is wrapped in a `Vector` object that provides methods for accessing values and comparing vectors.

### Basic Vector Operations

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs('Sample text for embedding')
    ->get();

$vector = $response->first();

// Get the raw float array
$values = $vector->values();
echo "Dimensions: " . count($values) . "\n";

// Get the vector's index/ID in the response
$id = $vector->id();
```

### Comparing Vectors

The `Vector` class supports three distance metrics for comparing embeddings:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\Data\Vector;

$embeddings = Embeddings::using('openai');

$response = $embeddings
    ->withInputs([
        'The cat sat on the mat.',
        'A feline rested on the rug.',
        'The stock market crashed today.',
    ])
    ->get();

$vectors = $response->vectors();

// Cosine similarity (higher = more similar, range: -1 to 1)
$similarity = $vectors[0]->compareTo($vectors[1], Vector::METRIC_COSINE);
echo "Cat/Feline similarity: " . round($similarity, 4) . "\n";

$similarity = $vectors[0]->compareTo($vectors[2], Vector::METRIC_COSINE);
echo "Cat/Stock similarity: " . round($similarity, 4) . "\n";

// Euclidean distance (lower = more similar)
$distance = $vectors[0]->compareTo($vectors[1], Vector::METRIC_EUCLIDEAN);
echo "Euclidean distance: " . round($distance, 4) . "\n";

// Dot product
$dot = $vectors[0]->compareTo($vectors[1], Vector::METRIC_DOT_PRODUCT);
echo "Dot product: " . round($dot, 4) . "\n";
```

You can also use the static methods directly on float arrays:

```php
<?php

use Cognesy\Polyglot\Embeddings\Data\Vector;

$similarity = Vector::cosineSimilarity($arrayA, $arrayB);
$distance = Vector::euclideanDistance($arrayA, $arrayB);
$dot = Vector::dotProduct($arrayA, $arrayB);
```

## Finding Similar Documents

The `EmbedUtils` class provides a convenient `findSimilar()` method that embeds a query and a set of documents in a single request, then ranks documents by cosine similarity:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\Utils\EmbedUtils;

$embeddings = Embeddings::using('openai');

$documents = [
    'PHP is a popular server-side scripting language.',
    'Python is widely used in data science and AI.',
    'Laravel is a PHP framework for web applications.',
    'TensorFlow is a machine learning library.',
    'Composer manages PHP dependencies.',
];

$results = EmbedUtils::findSimilar(
    embeddings: $embeddings,
    query: 'PHP web development tools',
    documents: $documents,
    topK: 3,
);

foreach ($results as $result) {
    echo round($result['similarity'], 4) . " - " . $result['content'] . "\n";
}
```

## Switching Between Providers

The same code works across providers -- just change the preset name:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$text = 'Artificial intelligence is transforming industries worldwide.';

// OpenAI
$openaiVector = Embeddings::using('openai')
    ->withInputs($text)
    ->first();
echo "OpenAI dimensions: " . count($openaiVector->values()) . "\n";

// Cohere
$cohereVector = Embeddings::using('cohere')
    ->withInputs($text)
    ->first();
echo "Cohere dimensions: " . count($cohereVector->values()) . "\n";

// Mistral
$mistralVector = Embeddings::using('mistral')
    ->withInputs($text)
    ->first();
echo "Mistral dimensions: " . count($mistralVector->values()) . "\n";
```

## Provider-Specific Options

Different providers support additional options that you can pass through `withOptions()`:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

// OpenAI: request specific dimensions and encoding format
$response = Embeddings::using('openai')
    ->withModel('text-embedding-3-large')
    ->withInputs('Sample text')
    ->withOptions([
        'encoding_format' => 'float',
        'dimensions' => 512,
    ])
    ->get();

// Cohere: specify input type and truncation behavior
$response = Embeddings::using('cohere')
    ->withInputs('Sample text')
    ->withOptions([
        'input_type' => 'classification',
        'truncate' => 'END',
    ])
    ->get();
```

## Custom Configuration

When you need full control over the connection parameters, create an `EmbeddingsConfig` directly:

```php
<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;

$config = new EmbeddingsConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: getenv('OPENAI_API_KEY'),
    endpoint: '/embeddings',
    model: 'text-embedding-3-large',
    dimensions: 3072,
    maxInputs: 100,
    driver: 'openai',
);

$vector = Embeddings::fromConfig($config)
    ->withInputs('Custom configuration example')
    ->first();

echo "Generated embedding with " . count($vector->values()) . " dimensions.\n";
```

You can also load configuration from a DSN string:

```php
<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;

$config = EmbeddingsConfig::fromDsn('openai://model=text-embedding-3-large');
$embeddings = Embeddings::fromConfig($config);
```
