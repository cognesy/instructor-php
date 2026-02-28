---
title: Working with Embeddings
description: 'Generate vectors and use embeddings effectively in Polyglot.'
---

## Create an embeddings facade

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings();
$openaiEmbeddings = Embeddings::using('openai');
```

`Embeddings` is request-focused. Runtime/provider/http/event wiring belongs to `EmbeddingsRuntime`.


## Generate embeddings

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$result = Embeddings::using('openai')
    ->with('The quick brown fox jumps over the lazy dog.')
    ->get();

$vector = $result->first()?->values() ?? [];
echo count($vector);
```


## Generate embeddings for multiple texts

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$documents = [
    'The quick brown fox jumps over the lazy dog.',
    'Machine learning models process text into vectors.',
    'Embeddings capture semantic relationships.',
];

$result = Embeddings::using('openai')
    ->withInputs($documents)
    ->get();

foreach ($result->vectors() as $i => $vector) {
    echo "Document {$i} dimensions: " . count($vector->values()) . PHP_EOL;
}
```


## Access results

```php
$response = $embeddings->with('Sample text')->get();
$firstVector = $response->first();
$allVectors = $response->vectors();
$values = $response->toValuesArray();
$usage = $response->usage();
```


## Switch providers

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$text = 'Artificial intelligence is transforming industries.';

$openai = Embeddings::using('openai')->with($text)->get();
$cohere = Embeddings::using('cohere')->with($text)->get();
$mistral = Embeddings::using('mistral')->with($text)->get();

printf("OpenAI dims: %d\n", count($openai->first()?->values() ?? []));
printf("Cohere dims: %d\n", count($cohere->first()?->values() ?? []));
printf("Mistral dims: %d\n", count($mistral->first()?->values() ?? []));
```


## Provider-specific options

```php
$openaiResponse = Embeddings::using('openai')->with(
    input: ['Sample text'],
    options: [
        'encoding_format' => 'float',
        'dimensions' => 512,
    ],
)->get();

$cohereResponse = Embeddings::using('cohere')->with(
    input: ['Sample text'],
    options: [
        'input_type' => 'classification',
        'truncate' => 'END',
    ],
)->get();
```


## Advanced runtime assembly

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

$provider = EmbeddingsProvider::new()->withPreset('openai');
$runtime = EmbeddingsRuntime::fromProvider($provider);

$embeddings = Embeddings::fromRuntime($runtime);
```
