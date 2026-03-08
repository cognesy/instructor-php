---
title: JSON Schema Responses
description: 'Request native JSON schema responses with Polyglot.'
---

Use `responseFormat: ['type' => 'json_schema', ...]` when the provider supports native schema-constrained output.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$schema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'population' => ['type' => 'integer'],
        'founded' => ['type' => 'integer'],
    ],
    'required' => ['name', 'population', 'founded'],
];

$data = Inference::using('openai')
    ->with(
        messages: 'Return city data for Paris as JSON.',
        responseFormat: [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'city_data',
                'schema' => $schema,
                'strict' => true,
            ],
        ],
    )
    ->asJsonData();
```

If the provider does not support native JSON schema response formats, Polyglot does not emulate them. Use Instructor for higher-level fallback strategies.
