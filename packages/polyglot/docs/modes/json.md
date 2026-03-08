---
title: JSON Object Responses
description: 'Request native JSON object responses with Polyglot.'
---

Use `responseFormat: ['type' => 'json_object']` when the provider supports native JSON object output.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: 'Return JSON with name, population, and founded year for Paris.',
        responseFormat: ['type' => 'json_object'],
        options: ['max_tokens' => 128],
    )
    ->asJsonData();
```

This requests JSON output, but strict schema enforcement depends on provider support. Use native JSON schema response formats when you need strict schema validation.
