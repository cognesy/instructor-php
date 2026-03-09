---
title: JSON Schema Responses
description: Request native schema-constrained JSON when the provider supports it.
---

Use `responseFormat` with `type: json_schema` for native schema-aware output.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: 'Return a city record as JSON.',
        responseFormat: [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'city_record',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'country' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'country'],
                ],
                'strict' => true,
            ],
        ],
    )
    ->asJsonData();
```

Polyglot forwards the native schema request. It does not emulate schema enforcement for providers that lack it.
