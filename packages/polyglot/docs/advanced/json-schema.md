---
title: Building JSON Schemas
description: Use arrays or the optional schema helper to build response schemas.
---

You can pass schema arrays directly in `responseFormat`.
If you prefer a builder, Polyglot also ships with `Cognesy\Utils\JsonSchema\JsonSchema` through its utilities dependency.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name'),
        JsonSchema::string('country'),
    ],
    requiredProperties: ['name', 'country'],
);

$data = Inference::using('openai')
    ->with(
        messages: 'Return a city record as JSON.',
        responseFormat: [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'city_record',
                'schema' => $schema->toJsonSchema(),
            ],
        ],
    )
    ->asJsonData();
```

This only helps you build the schema payload.
Provider-native enforcement still depends on the selected driver and model.
