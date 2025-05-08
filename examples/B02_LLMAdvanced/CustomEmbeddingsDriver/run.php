---
title: 'Custom Embeddings Driver'
docname: 'custom_embeddings_driver'
---

## Overview

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

$bestMatches = (new Embeddings)->withConnection($connection)->findSimilar(
    query: $query,
    documents: $documents,
    topK: 3
);

echo "\n[$connection]\n";
dump($bestMatches);
?>
```
