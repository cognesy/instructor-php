---
title: 'Data Model'
description: 'Choose the shape you want back from the model.'
---

The response model is the contract between your code and the LLM.

## Prefer Plain PHP Classes

For most cases, use a class with public typed properties.

```php
final class OrderSummary {
    public string $customer;
    public int $itemCount;
}
// @doctest id="02bf"
```

That is usually enough. Instructor builds a schema from the class and deserializes the response back into it.

## Other Supported Shapes

- class strings such as `OrderSummary::class`
- object instances
- JSON schema arrays
- helper wrappers such as `Scalar`, `Sequence`, and `Maybe`

## Nested Objects And Enums

Nested objects and enums are part of the normal path. If your class graph is simple and typed, it is usually a good fit.

## Keep Models Focused

- Use public typed properties
- Keep names clear
- Put validation close to the model
- Prefer small result types over large catch-all objects
