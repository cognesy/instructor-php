# Embeddings

Embeddings are a way to represent words in a continuous vector space. They are a key component
of many LLM-based solutions, and are used to represent text (or multimodal data) in a way that captures
their meaning and relationships.

`Embeddings` class offers access to embeddings APIs and convenient methods to find top K vectors
or documents most similar to provided query.

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
use Cognesy\Polyglot\Embeddings\Embeddings;

$docs = ['Computer vision models are used to analyze images and videos.'];

$embedding = (new Embeddings)
    ->withConnection('openai')
    ->create(input: $docs)
    ->all();
?>
```
