# Polyglot Package

Unified LLM connectivity layer for InstructorPHP.

It provides two facades:

- `Inference` for chat/completion responses
- `Embeddings` for vector generation

## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

$text = Inference::using('openai')
    ->withModel('gpt-5.6')
    ->withMessages('Write one short sentence about PHP.')
    ->withReasoning(ReasoningSelection::effort(ReasoningEffort::Medium))
    ->get();
```

## Documentation

- `packages/polyglot/docs/quickstart.md`
- `packages/polyglot/docs/essentials/inference-class.md`
- `packages/polyglot/docs/essentials/reasoning.md`
- `packages/polyglot/docs/embeddings/overview.md`
- `packages/polyglot/docs/_meta.yaml`
