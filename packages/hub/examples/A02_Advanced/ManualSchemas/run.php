---
title: 'Manual Schema Building'
docname: 'manual_schemas'
---

## Overview

While InstructorPHP can automatically generate schemas from PHP classes,
you can also build schemas manually using the `JsonSchema` API.

This provides full control over the JSON Schema structure and is useful for:
- Dynamic schemas determined at runtime
- Provider-specific optimizations
- Legacy JSON Schema integration
- Performance-sensitive scenarios

See more: [Manual Schema Building](../../../packages/instructor/docs/advanced/manual_schemas.md)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Instructor\StructuredOutput;

// Build schema manually (no PHP class needed)
$userSchema = JsonSchema::object(
    name: 'User',
    description: 'User information extracted from text',
    properties: [
        JsonSchema::string(
            name: 'name',
            description: 'Full name of the user'
        ),
        JsonSchema::integer(
            name: 'age',
            description: 'Age in years'
        ),
        JsonSchema::string(
            name: 'email',
            description: 'Email address'
        ),
        JsonSchema::enum(
            name: 'role',
            enumValues: ['admin', 'user', 'guest'],
            description: 'User role'
        ),
        JsonSchema::object(
            name: 'address',
            description: 'User address',
            properties: [
                JsonSchema::string(name: 'street'),
                JsonSchema::string(name: 'city'),
                JsonSchema::string(name: 'zip'),
            ],
            requiredProperties: ['city']
        ),
        JsonSchema::collection(
            name: 'skills',
            description: 'List of skills',
            itemSchema: JsonSchema::string()
        ),
    ],
    requiredProperties: ['name', 'email'],
    additionalProperties: false
);

$text = <<<TEXT
    John Doe (john.doe@example.com) is a 35-year-old admin user. He lives in
    New York at 123 Main St, NYC, 10001. His skills include PHP, JavaScript,
    and Docker.
    TEXT;

print("INPUT:\n$text\n\n");

// Use manual schema with StructuredOutput - returns array when no class is specified
$user = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: $userSchema,
    )
    ->get();

print("OUTPUT:\n");
print("Name: " . $user['name'] . "\n");
print("Email: " . $user['email'] . "\n");
print("Age: " . $user['age'] . "\n");
print("Role: " . $user['role'] . "\n");
print("Address: " . $user['address']['city'] . ", " . $user['address']['zip'] . "\n");
print("Skills: " . implode(", ", $user['skills']) . "\n");

print("\n");
print("COMPARISON: Manual vs Reflection\n");
print("=================================\n");
print("✅ Manual schema: Full control, no class needed\n");
print("✅ Reflection (from class): Concise, type-safe, single source of truth\n");
print("\n");
print("Choose manual schemas when:\n");
print("- Schema is determined at runtime\n");
print("- You need provider-specific optimizations\n");
print("- Working with legacy JSON Schema specs\n");
print("- Reflection overhead is a concern\n");
?>
```

## Advanced Example: Dynamic Schema

```php
<?php
require 'examples/boot.php';

// Build schema dynamically based on user input
function buildDynamicSchema(array $fields): JsonSchema {
    $properties = [];

    foreach ($fields as $field) {
        $properties[] = match($field['type']) {
            'string' => JsonSchema::string($field['name'], $field['description'] ?? ''),
            'int' => JsonSchema::integer($field['name'], $field['description'] ?? ''),
            'float' => JsonSchema::number($field['name'], $field['description'] ?? ''),
            'bool' => JsonSchema::boolean($field['name'], $field['description'] ?? ''),
            default => JsonSchema::string($field['name']),
        };
    }

    return JsonSchema::object(
        name: 'DynamicData',
        properties: $properties,
        requiredProperties: array_column($fields, 'name')
    );
}

// User-defined fields at runtime
$userFields = [
    ['name' => 'product', 'type' => 'string', 'description' => 'Product name'],
    ['name' => 'quantity', 'type' => 'int', 'description' => 'Quantity ordered'],
    ['name' => 'price', 'type' => 'float', 'description' => 'Unit price'],
    ['name' => 'inStock', 'type' => 'bool', 'description' => 'Is in stock'],
];

$schema = buildDynamicSchema($userFields);

$data = (new StructuredOutput)
    ->with(
        messages: 'Extract: Laptop, 2 units at $999.99 each, currently in stock',
        responseModel: $schema,
    )
    ->get();

print("Dynamic extraction result:\n");
print("Product: " . $data['product'] . "\n");
print("Quantity: " . $data['quantity'] . "\n");
print("Price: $" . $data['price'] . "\n");
print("In Stock: " . ($data['inStock'] ? 'Yes' : 'No') . "\n");
?>
```
