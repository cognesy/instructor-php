---
title: JSON Object Responses
description: Ask the provider for native JSON object output.
---

Use `responseFormat(['type' => 'json_object'])` when the provider supports native JSON object responses.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->withMessages('Return JSON with keys "name" and "role".')
    ->withResponseFormat(['type' => 'json_object'])
    ->asJsonData();
```

`asJsonData()` only decodes the returned content. Validation rules still depend on the provider and your prompt.
