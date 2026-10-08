# Polyglot Package

Unified LLM connectivity layer for InstructorPHP.

It provides three established operation facades:

- `Inference` for chat/completion responses
- `Embeddings` for vector generation
- `Decision` for typed structured decisions (`Noul`, `Choice`, and `Score`)

An opt-in `BatchInference` facade is available as an early access feature. It
submits provider-native asynchronous jobs and resumes them from a saved
reference. See the
[batch inference guide](docs/batch-inference/overview.md) for submission,
status, cancellation, results, listing, and provider limitations.

## Example

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

$message = Inference::using('openai')
    ->withModel('gpt-5.6')
    ->withMessages('Write one short sentence about PHP.')
    ->withReasoning(ReasoningSelection::effort(ReasoningEffort::Medium))
    ->get();

echo $message->content()->toString();
```

## Documentation

- `packages/polyglot/docs/quickstart.md`
- `packages/polyglot/docs/essentials/inference-class.md`
- `packages/polyglot/docs/essentials/reasoning.md`
- `packages/polyglot/docs/embeddings/overview.md`
- `packages/polyglot/docs/decision/overview.md`
- `packages/polyglot/docs/decision/questions.md`
- `packages/polyglot/docs/decision/responses.md`
- `packages/polyglot/docs/decision/runtime.md`
- `packages/polyglot/docs/decision/classifier-dev.md`
- `packages/polyglot/docs/decision/clef.md`
- `packages/polyglot/docs/decision/fastino.md`
- `packages/polyglot/docs/decision/jeff.md`
- `packages/polyglot/docs/decision/laya.md`
- `packages/polyglot/docs/decision/perplexity.md`
- `packages/polyglot/docs/decision/evaluation.md`
- `packages/polyglot/docs/decision/errors-testing.md`
- `packages/polyglot/docs/_meta.yaml`
