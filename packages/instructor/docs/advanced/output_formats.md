---
title: Output Formats
description: 'Keep one schema and choose how the result is materialized.'
---

The response model defines the schema. Output format controls how the result is returned.

## Built-In Options

- `intoArray()`
- `intoInstanceOf(...)`
- `intoObject(...)`

```php
$data = (new StructuredOutput)
    ->withResponseClass(User::class)
    ->intoArray()
    ->withMessages('Jane is 31 years old.')
    ->get();
```

Use this when one schema should serve multiple consumers.
