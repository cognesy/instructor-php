---
title: Demonstrations
description: 'Guide the model with a small number of examples.'
---

Use demonstrations when the shape is clear but the extraction style benefits from one or two examples.

```php
use Cognesy\Instructor\Extras\Example\Example;

$structured = (new StructuredOutput)
    ->withExamples([
        Example::fromText('Jane, 31', ['name' => 'Jane', 'age' => 31]),
    ]);
```

Keep examples short and representative. They should clarify the task, not replace the prompt.
