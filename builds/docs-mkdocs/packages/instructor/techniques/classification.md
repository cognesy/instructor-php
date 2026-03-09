---
title: Classification
description: 'A simple pattern for turning text into categories.'
---

Classification works well when the result shape is small and explicit.

Use an enum-backed or string-backed field in a response model, then ask the model to choose the best value.

```php
final class TicketLabel {
    public string $category;
}
// @doctest id="e9e1"
```

Keep the schema narrow. Classification quality usually improves when the model has fewer valid shapes to choose from.
