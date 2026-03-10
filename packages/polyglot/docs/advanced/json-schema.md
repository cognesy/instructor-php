---
title: Building JSON Schemas
description: Use arrays or the schema builder to define structured response formats.
---

When you need structured output from an LLM -- a JSON object with specific fields and types --
you pass a `responseFormat` that describes the expected shape. You can build this format as a
plain array, or use Polyglot's `JsonSchema` builder for a more expressive, composable approach.

Native JSON Schema enforcement depends on the selected driver and model. If your provider does
not support `json_schema` response format natively, consider using JSON mode or Markdown-JSON
mode for best-effort output.


## Why Use JsonSchema?

The `JsonSchema` builder offers several advantages over hand-crafting schema arrays:

- **Type safety** -- factory methods ensure each node has the correct structure
- **Composability** -- define sub-schemas once and embed them in multiple places
- **Readability** -- a fluent API makes complex schemas easy to scan
- **Conversion** -- convert the same schema to a response format or a tool/function definition


## Quick Start

Here is a minimal example that requests structured city data from an LLM:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', description: 'City name'),
        JsonSchema::integer('population', description: 'City population'),
        JsonSchema::integer('founded', description: 'Founding year'),
    ],
    requiredProperties: ['name', 'population', 'founded'],
);

$data = Inference::using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What is the capital of France? Respond with JSON data.'],
        ],
        responseFormat: [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'city_data',
                'schema' => $schema->toJsonSchema(),
                'strict' => true,
            ],
        ],
        options: ['max_tokens' => 64],
    )
    ->asJsonData();
```

The `JsonSchema` class only helps you build the schema payload. Polyglot passes it to the
provider, and the provider decides how strictly to enforce it.


## Available Types

The `JsonSchema` class provides static factory methods for every JSON Schema primitive.

### String

```php
$name = JsonSchema::string(
    name: 'full_name',
    description: 'The user\'s full name',
);
```

### Integer and Number

```php
$age = JsonSchema::integer('age', description: 'Age in years');
$price = JsonSchema::number('price', description: 'Product price');
```

### Boolean

```php
$active = JsonSchema::boolean('is_active', description: 'Whether the account is active');
```

### Array

Arrays require an `itemSchema` that describes the type of each element:

```php
$tags = JsonSchema::array(
    name: 'tags',
    description: 'List of tags',
    itemSchema: JsonSchema::string(),
);
```

Arrays can also contain complex objects:

```php
$hobbies = JsonSchema::array(
    name: 'hobbies',
    description: 'List of user hobbies',
    itemSchema: JsonSchema::object(
        properties: [
            JsonSchema::string('name', 'Hobby name'),
            JsonSchema::string('description', 'Hobby description', nullable: true),
            JsonSchema::integer('years_experience', 'Years of experience', nullable: true),
        ],
        requiredProperties: ['name', 'description', 'years_experience'],
    ),
);
```

### Enum

Enums restrict a field to a fixed set of string or integer values:

```php
$status = JsonSchema::enum(
    name: 'status',
    description: 'Account status',
    enumValues: ['active', 'inactive', 'pending'],
);
```

### Object

Objects define nested structures with named properties:

```php
$profile = JsonSchema::object(
    name: 'profile',
    description: 'User profile',
    properties: [
        JsonSchema::string('username', 'Unique username'),
        JsonSchema::string('bio', 'Short biography'),
        JsonSchema::integer('joined_year', 'Year joined'),
    ],
    requiredProperties: ['username'],
);
```


## Required and Nullable Fields

**Required** and **nullable** are independent concepts:

- A **required** field must be present in the output.
- A **nullable** field may contain a `null` value.
- A field can be both required and nullable (must be present, but may be null).
- A field can be optional and non-nullable (when present, cannot be null).

Required fields are specified at the object level:

```php
$user = JsonSchema::object(
    properties: [
        JsonSchema::string('email', 'Primary email'),
        JsonSchema::string('name', 'Full name'),
        JsonSchema::string('bio', 'Biography'),
    ],
    requiredProperties: ['email', 'name'],
);
```

Nullable fields are specified on individual properties:

```php
$bio = JsonSchema::string('bio', 'Optional biography', nullable: true);
```

### OpenAI Strict Mode

When working with OpenAI in strict mode, all fields must be listed as required. Use
`nullable: true` to indicate fields whose values are optional:

```php
$user = JsonSchema::object(
    properties: [
        JsonSchema::string('email', 'Required email'),
        JsonSchema::string('bio', 'Optional biography', nullable: true),
    ],
    requiredProperties: ['email', 'bio'], // Both required, but bio can be null
);
```

### Common Patterns

```php
// Required and non-nullable (most strict)
// requiredProperties: ['email']
JsonSchema::string('email', 'Primary email', nullable: false);

// Required but nullable (must be present, can be null)
// requiredProperties: ['bio']
JsonSchema::string('bio', 'User bio', nullable: true);

// Optional and non-nullable (can be omitted, but if present cannot be null)
// requiredProperties: [] (does not include 'phone')
JsonSchema::string('phone', 'Phone number', nullable: false);

// Optional and nullable (most permissive)
// requiredProperties: [] (does not include 'website')
JsonSchema::string('website', 'Personal website', nullable: true);
```


## Nested Schemas

For complex structures, define child schemas first and embed them into parent schemas:

```php
<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

$address = JsonSchema::object(
    name: 'address',
    properties: [
        JsonSchema::string('street', 'Street address'),
        JsonSchema::string('city', 'City name'),
        JsonSchema::string('postal_code', 'Postal/ZIP code'),
        JsonSchema::string('country', 'Country name'),
    ],
    requiredProperties: ['street', 'city', 'postal_code', 'country'],
);

$contact = JsonSchema::object(
    name: 'contact',
    properties: [
        JsonSchema::string('email', 'Email address'),
        JsonSchema::string('phone', 'Phone number', nullable: true),
    ],
    requiredProperties: ['email', 'phone'],
);

$user = JsonSchema::object(
    name: 'user',
    properties: [
        JsonSchema::string('name', 'Full name'),
        $address,
        $contact,
        JsonSchema::array('hobbies', 'User hobbies', itemSchema: JsonSchema::string()),
        JsonSchema::enum('status', 'Account status', enumValues: ['active', 'inactive']),
    ],
    requiredProperties: ['name', 'address', 'contact', 'status'],
);
```


## Fluent API

The `JsonSchema` class supports method chaining for cases where you want to build schemas
incrementally:

```php
$tags = JsonSchema::array('tags')
    ->withItemSchema(JsonSchema::string())
    ->withDescription('A list of tags')
    ->withNullable(true);
```

Available fluent methods include:

| Method | Description |
|---|---|
| `withName(string $name)` | Set the schema name |
| `withDescription(string $description)` | Set the description |
| `withTitle(string $title)` | Set the title |
| `withNullable(bool $nullable)` | Mark as nullable |
| `withMeta(array $meta)` | Attach custom metadata |
| `withEnumValues(?array $enum)` | Set enum values |
| `withProperties(?array $properties)` | Set object properties |
| `withItemSchema(JsonSchema $itemSchema)` | Set array item schema |
| `withRequiredProperties(?array $required)` | Set required property names |
| `withAdditionalProperties(bool $flag)` | Allow or disallow additional properties |


## Converting Schemas

The `JsonSchema` class provides methods to convert schemas into different output formats:

```php
// Convert to a plain array
$array = $schema->toArray();

// Convert to a JSON Schema document (suitable for responseFormat)
$jsonSchema = $schema->toJsonSchema();

// Convert to a function/tool call definition
$functionCall = $schema->toFunctionCall(
    functionName: 'getUserProfile',
    functionDescription: 'Gets the user profile information',
    strict: true,
);
```


## Accessing Schema Properties

You can inspect any schema programmatically:

```php
$schema->type();                    // Get schema type (e.g. 'object')
$schema->name();                    // Get schema name
$schema->description();             // Get description
$schema->title();                   // Get title
$schema->isNullable();              // Check if nullable
$schema->requiredProperties();      // Get required property names
$schema->properties();              // Get all property schemas
$schema->property('name');          // Get a specific property schema
$schema->itemSchema();              // Get item schema (for arrays)
$schema->enumValues();              // Get enum values
$schema->hasAdditionalProperties(); // Check if additional properties allowed
$schema->meta();                    // Get all meta fields
$schema->meta('key');               // Get a specific meta field
```


## Meta Fields

You can attach custom meta fields to schemas. These are rendered with an `x-` prefix in the
JSON Schema output:

```php
$username = JsonSchema::string(
    name: 'username',
    description: 'The username',
    meta: [
        'min_length' => 3,
        'max_length' => 50,
        'pattern' => '^[a-zA-Z0-9_]+$',
    ],
);
```

In the generated schema, these become `x-min_length`, `x-max_length`, and `x-pattern`.
Meta fields are useful for passing hints to post-processing validation or documentation
generators.


## Using Schemas as Tool Parameters

The `toFunctionCall()` method generates a tool/function definition that you can pass directly
to the `tools` parameter of an inference request:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

$params = JsonSchema::object(
    properties: [
        JsonSchema::string('location', 'City and country name'),
        JsonSchema::enum('unit', 'Temperature unit', enumValues: ['celsius', 'fahrenheit'], nullable: true),
    ],
    requiredProperties: ['location', 'unit'],
);

$tool = $params->toFunctionCall(
    functionName: 'getWeather',
    functionDescription: 'Get current weather for a location',
    strict: true,
);

$result = Inference::using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What is the weather like in Tokyo?'],
        ],
        tools: [$tool],
    )
    ->asToolCallJsonData();
```


## Full Example: User Profile Schema

Here is a complete example that defines a rich user profile schema and uses it to extract
structured data from an LLM:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

// Define child schemas
$address = JsonSchema::object(
    name: 'address',
    properties: [
        JsonSchema::string('street', 'Street address'),
        JsonSchema::string('city', 'City name'),
        JsonSchema::string('postal_code', 'Postal/ZIP code'),
        JsonSchema::string('country', 'Country name'),
    ],
    requiredProperties: ['street', 'city', 'postal_code', 'country'],
);

$contact = JsonSchema::object(
    name: 'contact',
    properties: [
        JsonSchema::string('email', 'Email address'),
        JsonSchema::string('phone', 'Phone number', nullable: true),
    ],
    requiredProperties: ['email', 'phone'],
);

$hobbies = JsonSchema::array(
    name: 'hobbies',
    description: 'List of user hobbies',
    itemSchema: JsonSchema::object(
        properties: [
            JsonSchema::string('name', 'Hobby name'),
            JsonSchema::string('description', 'Hobby description', nullable: true),
            JsonSchema::integer('years_experience', 'Years of experience', nullable: true),
        ],
        requiredProperties: ['name', 'description', 'years_experience'],
    ),
);

// Compose the parent schema
$userSchema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', 'User\'s full name'),
        JsonSchema::integer('age', 'User\'s age'),
        $address,
        $contact,
        $hobbies,
        JsonSchema::enum('status', 'Account status', enumValues: ['active', 'inactive', 'pending']),
    ],
    requiredProperties: ['name', 'age', 'address', 'contact', 'hobbies', 'status'],
);

// Use the schema with Inference
$userData = Inference::using('openai')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'Generate a profile for John Doe who lives in New York.'],
        ],
        responseFormat: [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'user_profile',
                'schema' => $userSchema->toJsonSchema(),
                'strict' => true,
            ],
        ],
    )
    ->asJsonData();

print_r($userData);
```


## Best Practices

**Write clear descriptions.** The description string guides the LLM toward correct output.
Be specific about format, length, and constraints.

```php
// Vague -- the LLM has little guidance
JsonSchema::string('name', 'the name');

// Specific -- the LLM understands the expected format
JsonSchema::string('name', 'The user\'s display name (2-50 characters)');
```

**Organize nested schemas.** Define child schemas as separate variables before embedding them
in a parent. This keeps your code readable and makes schemas reusable across different
request types.

**Be explicit about requirements.** Always specify both `requiredProperties` at the object
level and `nullable` on individual fields. Leaving them implicit creates ambiguity that
different providers handle differently.

**Use strict mode with OpenAI.** When targeting OpenAI, set `'strict' => true` in the
`json_schema` block and make all fields required. Use `nullable: true` for optional values.
This gives you the strongest possible enforcement of your schema.
