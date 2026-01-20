---
title: 'Embeddings'
docname: 'embeddings'
---

## Overview

`Embeddings` class offers access to embeddings APIs which allows to generate
vector representations of inputs. These embeddings can be used to compare
semantic similarity between inputs, e.g. to find relevant documents based
on a query.

`Embeddings` class supports following embeddings providers:
 - Azure
 - Cohere
 - Gemini
 - Jina
 - Mistral
 - OpenAI

Embeddings providers access details can be found and modified via
`/config/embed.php`.

To store and search across large sets of vector embeddings you may
want to use one of the popular vector databases: PGVector, Chroma,
Pinecone, Weaviate, Milvus, etc.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\Utils\EmbedUtils;

$query = "technology news";
$documents = [
    'Computer vision models are used to analyze images and videos.',
    'The bakers at the Nashville Bakery baked 200 loaves of bread on Monday morning.',
    'The new movie starring Tom Hanks is now playing in theaters.',
    'Famous soccer player Lionel Messi has arrived in town.',
    'News about the latest iPhone model has been leaked.',
    'New car model by Tesla is now available for pre-order.',
    'Philip K. Dick is an author of many sci-fi novels.',
];
$inputs = array_merge([$query], $documents);

$topK = 3;

// generate embeddings for query and documents (in a single request)
$response = (new Embeddings)
    ->using('openai')
    ->withInputs($inputs)
    ->get();

// get query and doc vectors from the response
[$queryVectors, $docVectors] = $response->split(1);

$queryVector = $queryVectors[0]
    ?? throw new \InvalidArgumentException('Query vector not found');

// calculate cosine similarities
$similarities = EmbedUtils::findTopK($queryVector, $docVectors, $topK);

// print documents most similar to the query
echo "Query: " . $query . PHP_EOL;
$count = 1;
foreach($similarities as $index => $similarity) {
    echo $count++;
    echo ': ' . $documents[$index];
    echo ' - cosine similarity to query = ' . $similarities[$index];
    echo PHP_EOL;
}

assert(!empty($similarities));
?>
```
