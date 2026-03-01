---
title: Overview of Inference
description: Quick, practical patterns for running inference with Polyglot.
---

`Inference` is the main facade for text generation and structured outputs.
Use it for one-off calls, provider switching, and streaming.

## Quick Start

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$text = (new Inference())
    ->withMessages('What is the capital of France?')
    ->get();
```

## Use a Specific Preset

Presets come from `config/llm.php`.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('anthropic')
    ->withMessages('Give me three deployment checklist items.')
    ->get();
```

## Override Model and Options Per Request

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$text = (new Inference())
    ->with(
        messages: 'Write a 2-line product summary.',
        model: 'gpt-4.1-nano',
        options: ['temperature' => 0.2, 'max_tokens' => 120],
    )
    ->get();
```

## Stream Output

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$stream = (new Inference())
    ->withMessages('Explain event sourcing in simple terms.')
    ->withStreaming()
    ->stream();

foreach ($stream->responses() as $partial) {
    echo $partial->contentDelta;
}
```

## Switch Providers at Runtime

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$presets = ['openai', 'anthropic', 'ollama'];

foreach ($presets as $preset) {
    $text = Inference::using($preset)
        ->withMessages('One sentence: what is dependency injection?')
        ->get();
}
```

## See Also

- [Inference class](./inference-class.md)
- [Request options](./request-options.md)
- [Streaming overview](../streaming/overview.md)
- [Custom configuration](../advanced/custom-config.md)
