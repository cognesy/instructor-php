---
title: Overview of Embeddings
description: 'Embeddings are vector representations of text and other data used for semantic search and retrieval.'
---

Embeddings map text (or multimodal data) into vectors where semantic similarity becomes numerical distance.
Polyglot exposes embeddings through a thin `Embeddings` facade and a runtime-first infrastructure layer.


## `Embeddings` facade

`Embeddings` is request-focused:
- Request composition (`withInputs`, `withModel`, `withOptions`, `with`, `withRequest`)
- Execution shortcuts (`get`, `vectors`, `first`)
- Runtime handoff (`runtime`, `withRuntime`)

Provider/config/http/event assembly lives in `EmbeddingsRuntime`.


## Supported providers

- Azure OpenAI
- Cohere
- Gemini
- Jina
- OpenAI

Provider settings come from config presets or DSN input.


## Basic usage

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$result = Embeddings::using('openai')
    ->withModel('text-embedding-3-small')
    ->withInputs(['The quick brown fox'])
    ->get();

$vector = $result->first()?->values() ?? [];
echo count($vector);
```


## Runtime selection

Use constructor sugar for common paths:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = Embeddings::using('openai');
$embeddings = Embeddings::fromDsn('driver=openai,model=text-embedding-3-small');
```

Inject a fully assembled runtime for advanced setup:

```php
<?php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

$http = (new HttpClientBuilder())
    ->withDriver(new MockHttpDriver())
    ->create();

$runtime = EmbeddingsRuntime::using(
    preset: 'openai',
    httpClient: $http,
);

$embeddings = Embeddings::fromRuntime($runtime);
```


## Request methods

```php
$embeddings
    ->withInputs(['text 1', 'text 2'])
    ->withModel('text-embedding-3-large')
    ->withOptions(['dimensions' => 1536]);

$embeddings->with(
    input: ['text 1', 'text 2'],
    options: ['dimensions' => 1536],
    model: 'text-embedding-3-large',
);
```


## Response shortcuts

```php
$response = $embeddings->get();
$vectors = $embeddings->vectors();
$first = $embeddings->first();
```


## Multiple providers

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$openai = Embeddings::using('openai')
    ->withModel('text-embedding-3-large')
    ->withInputs(['Document 1', 'Document 2'])
    ->vectors();

$cohere = Embeddings::using('cohere')
    ->withModel('embed-english-v3.0')
    ->withInputs(['Document 1', 'Document 2'])
    ->vectors();
```


## Custom runtime config

For explicit config/provider wiring, assemble runtime via `EmbeddingsRuntime::fromProvider(...)`.


## Driver registration

```php
use Cognesy\Polyglot\Embeddings\Embeddings;

Embeddings::registerDriver('custom-provider', CustomEmbeddingsDriver::class);
```
