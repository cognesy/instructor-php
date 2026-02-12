---
title: 'Pure Array Processing (No Classes)'
docname: 'pure_array_processing'
id: '0857'
---
## Overview

This example demonstrates extraction using ONLY arrays - no PHP classes,
no serialization, no deserialization. Just JSON Schema definition and array output.

This is useful when:
- Working with dynamic schemas defined at runtime
- Avoiding class creation overhead
- Integrating with systems that expect plain arrays
- Building schema-driven extraction pipelines


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\JsonSchema\JsonSchema;

echo "=== PURE ARRAY PROCESSING (NO CLASSES) ===\n\n";

// Define schema using JsonSchema fluent API - NO PHP CLASS NEEDED
$articleSchema = JsonSchema::object(
    name: 'article',
    description: 'An article with metadata',
    properties: [
        JsonSchema::string('title', 'Article title'),
        JsonSchema::string('author', 'Author name'),
        JsonSchema::integer('wordCount', 'Word count'),
        JsonSchema::array('tags', JsonSchema::string(), 'Article tags'),
    ],
    requiredProperties: ['title', 'author', 'wordCount', 'tags'],
);

// Extract data - no streaming, just simple get()
$article = (new StructuredOutput)
    ->with(
        messages: "Extract: 'Introduction to PHP 8.4' by Jane Doe, 1500 words, tags: php, tutorial, programming",
        responseModel: $articleSchema,  // <-- JsonSchema object, no class!
    )
    ->intoArray()  // Output as pure array
    ->get();

echo "=== RESULT (PURE ARRAY) ===\n";
dump($article);

// Verify it's a pure array - no objects involved
assert(is_array($article), 'Result must be array');
assert(!is_object($article), 'Result must NOT be object');
assert(is_string($article['title']), 'Title must be string');
assert(is_string($article['author']), 'Author must be string');
assert(is_int($article['wordCount']), 'WordCount must be int');
assert(is_array($article['tags']), 'Tags must be array');

// All array values are primitives
echo "\nField types:\n";
foreach ($article as $key => $value) {
    $type = gettype($value);
    echo "  $key: $type\n";
    assert(
        in_array($type, ['string', 'integer', 'array', 'double', 'boolean']),
        "All values must be primitives, got $type for $key"
    );
}

echo "\n✓ Pure array processing verified - no classes, no serialization!\n";
?>
```

## Expected Output

```
=== PURE ARRAY PROCESSING (NO CLASSES) ===

=== RESULT (PURE ARRAY) ===
array:4 [
  "title" => "Introduction to PHP 8.4"
  "author" => "Jane Doe"
  "wordCount" => 1500
  "tags" => array:3 [
    0 => "php"
    1 => "tutorial"
    2 => "programming"
  ]
]

Field types:
  title: string
  author: string
  wordCount: integer
  tags: array

✓ Pure array processing verified - no classes, no serialization!
```

## How It Works

1. **Schema definition**: `JsonSchema` fluent API defines the expected structure
2. **No PHP classes**: No `Article` class, no `@var` annotations, no type hints
3. **intoArray()**: Forces output to be plain PHP array
4. **Result**: Pure associative array with primitive values

## JsonSchema API

```php
// Basic types
JsonSchema::string('name', 'description')
JsonSchema::integer('age', 'description')
JsonSchema::number('price', 'description')
JsonSchema::boolean('active', 'description')

// Arrays
JsonSchema::array('tags', JsonSchema::string(), 'description')

// Objects (nested)
JsonSchema::object('address', [
    JsonSchema::string('street'),
    JsonSchema::string('city'),
], requiredProperties: ['street', 'city'])

// Enums
JsonSchema::enum('status', ['pending', 'active', 'closed'])
```

## Use Cases

- **Dynamic schemas**: Define extraction shapes at runtime
- **API-driven extraction**: Schema comes from external API/database
- **No-class pipelines**: Avoid PHP class overhead entirely
- **Array-first architectures**: When your system expects arrays throughout
