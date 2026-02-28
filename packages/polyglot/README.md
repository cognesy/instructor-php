# Overview

This directory contains the source code for the LLM connectivity layer of the Instructor library.

Contents:
 - LLM - provides integrations with various language model API providers
 - Embeddings - provides integrations with various embeddings API providers

`Inference` and `Embeddings` are thin request facades. Provider/config/http/event assembly lives in
`InferenceRuntime` and `EmbeddingsRuntime`.

Quick example:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$vectors = Embeddings::using('openai')
    ->withModel('text-embedding-3-small')
    ->withInputs(['hello world'])
    ->vectors();
```
