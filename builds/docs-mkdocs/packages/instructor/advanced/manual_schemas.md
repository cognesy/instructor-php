---
title: 'Manual Schemas'
description: 'Build JSON schemas programmatically without PHP classes.'
---

While Instructor can automatically generate schemas from PHP classes via reflection, you can also define schemas manually. This is useful when the data shape is determined at runtime, when you need fine-grained control over the exact schema sent to the LLM, or when you want to avoid the overhead of reflection.

## Using a Raw Schema Array

The simplest approach is to pass a plain JSON Schema array as the response model.

```php
use Cognesy\Instructor\StructuredOutput;

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
// @doctest id="ca93"
```

When a schema array is provided instead of a class name, the result is returned as an associative array. You can also call `->get()` which will return a `Structure` object with dynamic property access.

## Using the JsonSchema Builder

For more complex schemas, the `JsonSchema` class provides a fluent API with static factory methods for every JSON Schema type.

### Object Schemas

```php
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    name: 'User',
    description: 'User data',
    properties: [
        JsonSchema::string(name: 'name', description: 'User name'),
        JsonSchema::integer(name: 'age', description: 'User age'),
        JsonSchema::boolean(name: 'active', description: 'Is active'),
    ],
    requiredProperties: ['name', 'age'],
);
// @doctest id="b118"
```

### Primitive Types

```php
JsonSchema::string(name: 'email', description: 'Email address');
JsonSchema::integer(name: 'count', description: 'Number of items');
JsonSchema::number(name: 'price', description: 'Product price');
JsonSchema::boolean(name: 'verified', description: 'Is verified');
// @doctest id="89a9"
```

### Arrays and Collections

```php
JsonSchema::array(
    name: 'tags',
    itemSchema: JsonSchema::string(),
    description: 'List of tags',
);

JsonSchema::collection(
    name: 'users',
    itemSchema: JsonSchema::object(
        name: 'User',
        properties: [
            JsonSchema::string(name: 'name'),
            JsonSchema::integer(name: 'age'),
        ],
    ),
    description: 'List of users',
);
// @doctest id="0c80"
```

### Enums

```php
JsonSchema::enum(
    name: 'status',
    enumValues: ['pending', 'active', 'completed'],
    description: 'Order status',
);
// @doctest id="339a"
```

### Parsing an Existing Array

If you already have a JSON Schema as an array, you can parse it into a `JsonSchema` object for further manipulation.

```php
$schema = JsonSchema::fromArray([
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer'],
    ],
    'required' => ['name'],
]);
// @doctest id="5704"
```

## Using JsonSchema with StructuredOutput

`JsonSchema` implements `CanProvideJsonSchema`, so you can pass it directly as a response model.

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\JsonSchema\JsonSchema;

$userSchema = JsonSchema::object(
    name: 'User',
    properties: [
        JsonSchema::string(name: 'name'),
        JsonSchema::integer(name: 'age'),
    ],
    requiredProperties: ['name'],
);

$user = (new StructuredOutput)
    ->with(
        messages: 'Extract user: John Doe, 30 years old',
        responseModel: $userSchema,
    )
    ->get();
// @doctest id="d716"
```

## Complex Example

Nested schemas with multiple types compose naturally.

```php
$orderSchema = JsonSchema::object(
    name: 'Order',
    description: 'Customer order',
    properties: [
        JsonSchema::string(name: 'orderId', description: 'Unique order identifier'),
        JsonSchema::object(
            name: 'customer',
            description: 'Customer information',
            properties: [
                JsonSchema::string(name: 'name'),
                JsonSchema::string(name: 'email'),
            ],
            requiredProperties: ['name', 'email'],
        ),
        JsonSchema::collection(
            name: 'items',
            description: 'Order line items',
            itemSchema: JsonSchema::object(
                name: 'LineItem',
                properties: [
                    JsonSchema::string(name: 'product'),
                    JsonSchema::integer(name: 'quantity'),
                    JsonSchema::number(name: 'price'),
                ],
                requiredProperties: ['product', 'quantity', 'price'],
            ),
        ),
        JsonSchema::enum(
            name: 'status',
            enumValues: ['pending', 'shipped', 'delivered'],
            description: 'Order status',
        ),
    ],
    requiredProperties: ['orderId', 'customer', 'items', 'status'],
);

$order = (new StructuredOutput)
    ->with(
        messages: 'Extract order details from: ...',
        responseModel: $orderSchema,
    )
    ->get();
// @doctest id="779e"
```

## When to Use Manual Schemas

Manual schemas are the right choice when:

- **Dynamic shapes** -- the structure is determined at runtime based on user input or configuration
- **Provider optimization** -- you need to tweak schemas for specific LLM providers
- **Legacy integration** -- you are working with existing JSON Schema specifications
- **No class needed** -- the data shape is simple or used once, so defining a PHP class adds unnecessary ceremony

For most cases, defining a PHP class and letting Instructor generate the schema via reflection is simpler, type-safe, and easier to refactor. Choose the approach that best fits your use case.

## Best Practices

1. **Use meaningful descriptions** -- LLMs use property descriptions to understand what data to extract
2. **Mark required fields explicitly** -- do not rely on defaults
3. **Extract common sub-schemas** -- assign reusable parts to variables to keep schemas DRY
4. **Inspect generated output** -- call `$schema->toJsonSchema()` to verify the schema looks correct
