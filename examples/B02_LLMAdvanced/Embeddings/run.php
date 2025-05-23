---
title: 'Embeddings'
docname: 'embeddings'
---

## Overview

`Embeddings` class offers access to embeddings APIs and convenient methods
to find top K vectors or documents most similar to provided query.

`Embeddings` class supports following embeddings providers:
 - Azure
 - Cohere
 - Gemini
 - Jina
 - Mistral
 - OpenAI

Embeddings providers access details can be found and modified via
`/config/embed.php`.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\Embeddings;

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

$presets = [
    'azure',
    'cohere2',
    'gemini',
    'jina',
    'mistral',
    //'ollama',
    'openai'
];

foreach($presets as $preset) {
    $bestMatches = (new Embeddings)->using($preset)->findSimilar(
        query: $query,
        documents: $documents,
        topK: 3
    );

    echo "\n[$preset]\n";
    dump($bestMatches);
}
?>
```
