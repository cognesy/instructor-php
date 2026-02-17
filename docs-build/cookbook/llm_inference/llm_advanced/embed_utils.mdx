---
title: 'Embeddings utils'
docname: 'embed_utils'
id: '297f'
---
## Overview

`EmbedUtils` class offers convenient methods to find top K vectors
or documents most similar to provided query.

Check out the `EmbedUtils` class for more details.
 - `EmbedUtils::findTopK()`
 - `EmbedUtils::findSimilar()`

Embeddings providers access details can be found and modified via
`/config/embed.php`.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\Utils\EmbedUtils;

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
    'cohere',
    'gemini',
    'jina',
    'mistral',
    //'ollama',
    'openai'
];

foreach($presets as $preset) {
    $bestMatches = EmbedUtils::findSimilar(
        provider: EmbeddingsProvider::using($preset),
        query: $query,
        documents: $documents,
        topK: 3
    );

    echo "\n[$preset]\n";
    dump($bestMatches);
}
?>
```
