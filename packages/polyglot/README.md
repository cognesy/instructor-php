# Polyglot Package

Unified LLM connectivity layer for InstructorPHP.

It provides two facades:
- `Inference` for chat/completion responses
- `Embeddings` for vector generation

## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withModel('gpt-4o-mini')
    ->withMessages('Write one short sentence about PHP.')
    ->get();
```

## Documentation

- `packages/polyglot/docs/quickstart.md`
- `packages/polyglot/docs/essentials/inference-class.md`
- `packages/polyglot/docs/embeddings/overview.md`
- `packages/polyglot/docs/_meta.yaml`
