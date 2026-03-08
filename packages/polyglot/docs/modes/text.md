---
title: Plain Text Responses
description: 'Use Polyglot for plain text inference responses.'
---

Plain text is the default Polyglot response shape.

You do not need to set `responseFormat` for ordinary text generation.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$answer = Inference::using('openai')
    ->with(
        messages: 'What is the capital of France?',
        options: ['max_tokens' => 64],
    )
    ->get();
```
