---
title: Sequences
description: 'Extract collections of objects.'
---

Use `Sequence` when the result is a list of typed objects.

```php
use Cognesy\Instructor\Extras\Sequence\Sequence;

$people = (new StructuredOutput)
    ->with(messages: $text, responseModel: Sequence::of(Person::class))
    ->get();
```

`Sequence` gives you collection-style helpers such as `count()`, `first()`, `last()`, `get()`, and `all()`.

## Stream Completed Items

When streaming a sequence, `sequence()` yields completed items as they become stable:

```php
foreach ($stream->sequence() as $person) {
    // completed item
}
```
