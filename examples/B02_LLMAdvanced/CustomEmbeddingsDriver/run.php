---
title: 'Custom Embeddings Driver'
docname: 'custom_embeddings_driver'
---

## Overview

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Utils\Config\Env;

$documents = [
    'Computer vision models are used to analyze images and videos.',
    'The bakers at the Nashville Bakery baked 200 loaves of bread on Monday morning.',
    'The new movie starring Tom Hanks is now playing in theaters.',
    'Famous soccer player Lionel Messi has arrived in town.',
    'News about the latest iPhone model has been leaked.',
    'New car model by Tesla is now available for pre-order.',
    'Philip K. Dick is an author of many sci-fi novels.',
];

$query = "technology news";

$config = new EmbeddingsConfig(
    apiUrl: 'https://api.cohere.ai/v1',
    apiKey: Env::get('COHERE_API_KEY', ''),
    endpoint: '/embed',
    model: 'embed-multilingual-v3.0',
    dimensions: 1024,
    maxInputs: 96,
    httpClient: 'guzzle',
    providerType: 'cohere2',
);

$embeddings = (new Embeddings)->withConfig($config);

$bestMatches = $embeddings->findSimilar(
    query: $query,
    documents: $documents,
    topK: 3
);

dump($bestMatches);
?>
```
