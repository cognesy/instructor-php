---
title: Structured outputs with JsonSchema class
description: Learn how to use JSON Schemas to generate structured outputs using LLMs. 
---

JsonSchema is a powerful utility in the Polyglot library that enables developers to define structured data schemas for LLM interactions. This guide explains how to use JsonSchema to shape your LLM outputs and ensure consistent, typed responses from language models.

Native JSON Schema enforcement depends on provider support. If your provider does not support
`OutputMode::JsonSchema` natively, use `OutputMode::Json` or `OutputMode::MdJson` for best-effort output.

## Quick Start

Here's a simple example of how to use JsonSchema with Polyglot's Inference API:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\JsonSchema\JsonSchema;

// Define your schema
$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', description: 'City name'),
        JsonSchema::integer('population', description: 'City population'),
        JsonSchema::integer('founded', description: 'Founding year'),
    ],
    requiredProperties: ['name', 'population', 'founded'],
);

// Use the schema with Inference
$data = (new Inference)
    ->using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What is capital of France? Respond with JSON data.']
        ],
        responseFormat: [
            'type' => 'json_schema',
            'description' => 'City data',
            'json_schema' => [
                'name' => 'city_data',
                'schema' => $schema->toJsonSchema(),
                'strict' => true,
            ],
        ],
        options: ['max_tokens' => 64],
        mode: OutputMode::JsonSchema,
    )
    ->asJsonData();
```

## Why Use JsonSchema?

JsonSchema provides several benefits when working with LLMs:

1. **Type Safety**: Ensure LLM outputs conform to your expected data structure
2. **Data Validation**: Specify required fields and data types
3. **Structured Responses**: Get consistent, well-formatted data instead of raw text
4. **Complex Nesting**: Define deeply nested structures for sophisticated applications
5. **Better LLM Guidance**: Help the LLM understand exactly what format you need

## Available Types

### String

For text values of any length:

```php
use Cognesy\Utils\JsonSchema\JsonSchema;

$nameSchema = JsonSchema::string(
    name: 'full_name',
    description: 'The user\'s full name'
);
```

### Number & Integer

For numeric values:

```php
$ageSchema = JsonSchema::integer(
    name: 'age',
    description: 'The user\'s age in years'
);

$priceSchema = JsonSchema::number(
    name: 'price',
    description: 'Product price'
);
```

### Boolean

For true/false values:

```php
$activeSchema = JsonSchema::boolean(
    name: 'is_active',
    description: 'Whether the user account is active'
);
```

### Array

For lists of items:

```php
$tagsSchema = JsonSchema::array(
    name: 'tags',
    description: 'List of tags associated with the post',
    itemSchema: JsonSchema::string()
);
```

### Enum

For values from a specific set of options:

```php
$statusSchema = JsonSchema::enum(
    name: 'status',
    description: 'The current status of the post',
    enumValues: ['draft', 'published', 'archived']
);
```

### Object

For complex, nested data structures:

```php
$profileSchema = JsonSchema::object(
    name: 'profile',
    description: 'A user\'s public profile information',
    properties: [
        JsonSchema::string('username', 'The unique username'),
        JsonSchema::string('bio', 'A short biography'),
        JsonSchema::integer('joined_year', 'Year the user joined'),
    ],
    requiredProperties: ['username']
);
```

## Working with Required and Nullable Fields

### Required Fields

Required fields are specified at the object level using the `requiredProperties` parameter:

```php
$userSchema = JsonSchema::object(
    properties: [
        JsonSchema::string('email', 'Primary email address'),
        JsonSchema::string('name', 'User\'s full name'),
        JsonSchema::string('bio', 'User biography'),
    ],
    requiredProperties: ['email', 'name'] // email and name must be present
);
```

### Nullable Fields

Nullable fields are specified at the individual field level:

```php
$bioSchema = JsonSchema::string(
    name: 'bio',
    description: 'Optional user biography',
    nullable: true
);
```

## Understanding Required vs. Nullable

- **Required**: The field must be present in the data structure
- **Nullable**: The field can contain a null value
- A field can be both required and nullable (must be present, can be null)
- A field can be non-required and non-nullable (when present, cannot be null)

### Common Patterns

```php
// Required and Non-nullable (most strict)
JsonSchema::string('email', 'Primary email', nullable: false);
// requiredProperties: ['email']

// Required but Nullable (must be present, can be null)
JsonSchema::string('bio', 'User bio', nullable: true);
// requiredProperties: ['bio']

// Optional and Non-nullable (can be omitted, but if present cannot be null)
JsonSchema::string('phone', 'Phone number', nullable: false);
// requiredProperties: [] (doesn't include 'phone')

// Optional and Nullable (most permissive)
JsonSchema::string('website', 'Personal website', nullable: true);
// requiredProperties: [] (doesn't include 'website')
```

## Working with OpenAI and Other Providers

When working with OpenAI in strict mode, follow these guidelines:

```php
// For OpenAI strict mode: 
// - All fields should be required
// - Use nullable: true for optional fields
$userSchema = JsonSchema::object(
    properties: [
        JsonSchema::string('email', 'Required email address'),
        JsonSchema::string('bio', 'Optional biography', nullable: true),
    ],
    requiredProperties: ['email', 'bio'] // Note: bio is required but nullable
);
```

## Building Complex Schemas

For more complex data structures, you can nest schemas:

```php
// Define child schemas first
$addressSchema = JsonSchema::object(
    name: 'address',
    properties: [
        JsonSchema::string('street', 'Street address'),
        JsonSchema::string('city', 'City name'),
        JsonSchema::string('country', 'Country name'),
    ],
    requiredProperties: ['street', 'city', 'country']
);

// Use them in parent schemas
$userSchema = JsonSchema::object(
    name: 'user',
    properties: [
        JsonSchema::string('name', 'User name'),
        $addressSchema, // embed the address schema
    ],
    requiredProperties: ['name', 'address']
);
```

## Fluent API for Schema Creation

JsonSchema supports method chaining for a more fluent API:

```php
$schema = JsonSchema::array('tags')
    ->withItemSchema(JsonSchema::string())
    ->withDescription('A list of tags')
    ->withNullable(true);
```

Available methods include:
- `withName(string $name)`
- `withDescription(string $description)`
- `withTitle(string $title)`
- `withNullable(bool $nullable = true)`
- `withMeta(array $meta = [])`
- `withEnumValues(?array $enum = null)`
- `withProperties(?array $properties = null)`
- `withItemSchema(JsonSchema $itemSchema = null)`
- `withRequiredProperties(?array $required = null)`
- `withAdditionalProperties(bool $additionalProperties = false)`

## Accessing Schema Properties

JsonSchema provides various methods to access schema properties:

```php
$schema->type();                // Get schema type (e.g., 'object')
$schema->name();                // Get schema name
$schema->isNullable();          // Check if schema is nullable
$schema->requiredProperties();  // Get array of required properties
$schema->properties();          // Get array of all properties
$schema->property('name');      // Get specific property
$schema->itemSchema();          // Get item schema for array schemas
$schema->enumValues();          // Get enum values
$schema->hasAdditionalProperties(); // Check if additional properties are allowed
$schema->description();         // Get schema description
$schema->title();               // Get schema title
$schema->meta();                // Get all meta fields
$schema->meta('key');           // Get specific meta field
```

## Converting Schemas to Arrays and Function Calls

JsonSchema can be converted to arrays and function calls:

```php
// Convert to array
$schemaArray = $schema->toArray();

// Convert to JSON schema
$jsonSchema = $schema->toJsonSchema();

// Convert to function call (for tools/functions)
$functionCall = $schema->toFunctionCall(
    functionName: 'getUserProfile',
    functionDescription: 'Gets the user profile information',
    strict: true
);
```

## Meta Fields

You can add custom meta fields to your schemas:

```php
$schema = JsonSchema::string(
    name: 'username',
    description: 'The username',
    meta: [
        'min_length' => 3,
        'max_length' => 50,
        'pattern' => '^[a-zA-Z0-9_]+$',
    ]
);
```

Meta fields will be transformed to include the `x-` prefix when converted to arrays (e.g., `x-min_length`).

## Best Practices

1. **Clear Descriptions**: Write clear, concise descriptions for each field.

```php
// ❌ Not helpful
JsonSchema::string('name', 'the name');

// ✅ Much better
JsonSchema::string('name', 'The user\'s display name (2-50 characters)');
```

2. **Only Mark Required Fields**: Only mark fields as required if they're truly necessary.

3. **Organize Nested Schemas**: Keep your schemas organized when dealing with complex structures.

```php
// Define child schemas first for clarity
$addressSchema = JsonSchema::object(/*...*/);
$contactSchema = JsonSchema::object(/*...*/);

// Then use them in your parent schema
$userSchema = JsonSchema::object(
    properties: [$addressSchema, $contactSchema]
);
```

4. **Be Explicit About Requirements**: Specify both the nullable status and required fields for clarity.

## Full Example: Creating User Profile Schema

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\JsonSchema\JsonSchema;

// Define address schema
$addressSchema = JsonSchema::object(
    name: 'address',
    properties: [
        JsonSchema::string('street', 'Street address'),
        JsonSchema::string('city', 'City name'),
        JsonSchema::string('postal_code', 'Postal/ZIP code'),
        JsonSchema::string('country', 'Country name'),
    ],
    requiredProperties: ['city', 'country'],
);

// Define contact schema
$contactSchema = JsonSchema::object(
    name: 'contact',
    properties: [
        JsonSchema::string('email', 'Email address'),
        JsonSchema::string('phone', 'Phone number', nullable: true),
    ],
    requiredProperties: ['email', 'phone'],
);

// Define hobbies schema
$hobbiesSchema = JsonSchema::array(
    name: 'hobbies',
    description: 'List of user hobbies',
    itemSchema: JsonSchema::object(
        properties: [
            JsonSchema::string('name', 'Hobby name'),
            JsonSchema::string('description', 'Hobby description', nullable: true),
            JsonSchema::integer('years_experience', 'Years of experience', nullable: true),
        ],
        requiredProperties: ['name'],
    ),
);

// Define main user schema
$userSchema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', 'User\'s full name'),
        JsonSchema::integer('age', 'User\'s age'),
        $addressSchema,
        $contactSchema,
        $hobbiesSchema,
        JsonSchema::enum(
            'status',
            'Account status',
            enumValues: ['active', 'inactive', 'pending'],
        ),
    ],
    requiredProperties: ['name', 'age', 'address', 'contact', 'status'],
);

// Use the schema with Inference
$userData = (new Inference)
    ->using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'Generate a profile for John Doe who lives in New York.']
        ],
        responseFormat: [
            'type' => 'json_schema',
            'description' => 'User profile data',
            'json_schema' => [
                'name' => 'user_profile',
                'schema' => $userSchema->toJsonSchema(),
                'strict' => true,
            ],
        ],
        mode: OutputMode::JsonSchema,
    )
    ->asJsonData();

print_r($userData);
```

## Advanced: Creating Function Calls

JsonSchema can be used to define function/tool parameters for LLMs:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

// Define the schema for the function parameters
$weatherParamsSchema = JsonSchema::object(
    properties: [
        JsonSchema::string('location', 'City and country name'),
        JsonSchema::enum(
            'unit', 
            'Temperature unit', 
            enumValues: ['celsius', 'fahrenheit'],
            nullable: true
        ),
    ],
    requiredProperties: ['location'],
);

// Convert schema to function call format
$functionDefinition = $weatherParamsSchema->toFunctionCall(
    functionName: 'getWeather',
    functionDescription: 'Get the current weather for a location',
    strict: true
);

// Use with Polyglot's Inference API
$result = (new Inference)
    ->using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What\'s the weather like in Tokyo?']
        ],
        tools: [$functionDefinition],
        // Additional configuration...
    )
    ->create();
```
