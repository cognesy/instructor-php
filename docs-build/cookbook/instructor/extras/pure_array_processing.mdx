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
