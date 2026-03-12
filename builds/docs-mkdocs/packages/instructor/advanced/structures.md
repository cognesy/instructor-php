---
title: Structures
description: 'Define dynamic data shapes at runtime without PHP classes.'
---

When you need to define the shape of extracted data at runtime -- based on user input, configuration, or processing context -- PHP classes are not flexible enough. The `Structure` class from the `cognesy/dynamic` package solves this by letting you define arbitrary data shapes dynamically.

## When to Use Structures

Structures are the right choice when:

- The data shape is not known at compile time
- Users configure what fields to extract
- You need to adapt the extraction schema based on context
- Defining a PHP class for a one-off shape would be unnecessary ceremony

For static, known data shapes, a PHP class is simpler and provides better IDE support.

## Defining a Structure

Use `StructureFactory` to build a `Structure` from various sources. The most common approach is building from a JSON Schema array.

```php
use Cognesy\Dynamic\StructureFactory;

$factory = new StructureFactory();

$structure = $factory->fromJsonSchema([
    'type' => 'object',
    'x-title' => 'person',
    'description' => 'A person object',
    'properties' => [
        'name' => ['type' => 'string', 'description' => 'Name of the person'],
        'age' => ['type' => 'integer', 'description' => 'Age of the person'],
        'role' => [
            'type' => 'string',
            'enum' => ['manager', 'line'],
            'description' => 'Role of the person',
        ],
    ],
    'required' => ['name', 'age', 'role'],
]);
// @doctest id="c9c2"
```

### From a String Definition

For quick prototyping, you can define structures using a compact string syntax.

```php
$structure = $factory->fromString(
    name: 'person',
    typeString: 'name:string, age:int, role:string',
    description: 'A person object',
);
// @doctest id="75fe"
```

### From a PHP Class

You can also create a structure from an existing class, which is useful when you want to manipulate the schema dynamically after reflection.

```php
$structure = $factory->fromClass(Person::class);
// @doctest id="558b"
```

### From Key-Value Data

Infer the schema from sample data.

```php
$structure = $factory->fromArrayKeyValues('person', [
    'name' => 'Jane',
    'age' => 25,
    'active' => true,
]);
// @doctest id="a202"
```

## Extracting Data

Pass a `Structure` as the response model to `StructuredOutput`. The result is a `Structure` object with the extracted data.

```php
use Cognesy\Instructor\StructuredOutput;

$text = <<<TEXT
    Jane Doe lives in Springfield. She is 25 years old and works as a line worker.
TEXT;

$person = (new StructuredOutput)->with(
    messages: $text,
    responseModel: $structure,
)->get();

// Access properties directly via __get
echo $person->name;  // "Jane Doe"
echo $person->age;   // 25

// Or use get()
echo $person->get('role');  // "line"

// Convert to array
$data = $person->toArray();
// ['name' => 'Jane Doe', 'age' => 25, 'role' => 'line']
// @doctest id="4376"
```

If you prefer a raw array result, use `intoArray()`.

```php
$data = (new StructuredOutput)->with(
    messages: $text,
    responseModel: $structure,
)->intoArray()->get();
// @doctest id="ce10"
```

## Working with Structure Objects

Structure objects provide `get()` for reading properties and `set()` for creating modified copies. Direct property access via `__get` is also supported for reading.

```php
// Reading
$name = $person->get('name');
$name = $person->name;

// Writing (returns new instance -- Structure is immutable)
$updated = $person->set('name', 'John Doe');

// Check if property exists
$person->has('name'); // true

// Convert to array
$person->toArray();
// @doctest id="c94d"
```

Note that `Structure` is immutable. The `set()` method returns a new instance with the updated value, leaving the original unchanged. Direct property assignment via `__set` throws a `BadMethodCallException`.

## Alternative Approaches

If you do not need the full `Structure` class, Instructor offers simpler alternatives for dynamic schemas:

- **JSON Schema array** -- pass a raw schema array as the response model (see [Manual Schemas](manual_schemas.md))
- **`JsonSchema` builder** -- use the fluent `JsonSchema` API for programmatic schema construction
- **`Scalar`** -- extract a single typed value (string, integer, float, boolean, or enum)
- **`Sequence`** -- extract a list of typed objects

These cover most use cases without requiring the dynamic package.
