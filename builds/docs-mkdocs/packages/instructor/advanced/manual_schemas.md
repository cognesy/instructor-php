---
title: 'Manual Schemas'
description: 'Pass a JSON schema directly.'
---

When a class is not the right fit, pass a schema array to `withResponseJsonSchema(...)` or `withResponseModel(...)`.

```php
$schema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer'],
    ],
    'required' => ['name', 'age'],
];

$data = (new StructuredOutput)
    ->with(messages: 'Jane is 31 years old.', responseModel: $schema)
    ->getArray();
// @doctest id="f04a"
```

Use this path when the shape is dynamic or not worth modeling as a class.
