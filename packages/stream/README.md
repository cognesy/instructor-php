# Stream Package

Low-level stream processing utilities for InstructorPHP, built around transducers.

Use it to compose reusable transformations and run them over arrays, files, text, CSV/JSONL, HTTP chunk streams, or custom iterables.

## Example

```php
<?php

use Cognesy\Stream\Transformation;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;
use Cognesy\Stream\Transform\Limit\Transducers\TakeN;
use Cognesy\Stream\Transform\Map\Transducers\Map;

$result = Transformation::define(
    new Map(fn($x) => $x * 2),
    new Filter(fn($x) => $x > 5),
    new TakeN(3),
)->executeOn([1, 2, 3, 4, 5, 6]);
```

## Documentation

- `packages/stream/CHEATSHEET.md`
- `packages/stream/INTERNALS.md`
- `packages/stream/tests/`
