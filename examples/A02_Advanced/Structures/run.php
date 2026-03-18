---
title: 'Structures'
docname: 'structures'
id: '40e1'
tags:
  - 'advanced'
  - 'dynamic-schema'
  - 'structures'
---
## Overview

Structures let you define output shape at runtime.

Use `SchemaBuilder` to define the schema and pass a `Structure`
as response model.

If `Structure` is used as response model, Instructor returns a dynamic value
that can be converted with `->toArray()`.

See more: [Structures](../../structures.md)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\SchemaBuilder;

enum Role : string {
    case Manager = 'manager';
    case Line = 'line';
}

$book = SchemaBuilder::define('book', 'Favorite book data')
    ->string('author', 'Book author', required: false)
    ->string('title', 'Book title')
    ->schema();

$schema = SchemaBuilder::define('person', 'A person object')
    ->string('name', 'Name of the person')
    ->int('age', 'Age of the person')
    ->option('gender', ['male', 'female'], 'Gender of the person', required: false)
    ->shape('address', fn(SchemaBuilder $builder) => $builder
        ->string('street', 'Street name', required: false)
        ->string('city', 'City name')
        ->string('zip', 'Zip code', required: false), 'Address of the person')
    ->enum('role', Role::class, 'Role of the person')
    ->collection('favourite_books', $book, 'Favorite books of the person')
    ->schema();

$structure = Structure::fromSchema($schema);

$text = <<<TEXT
    Jane Doe lives in Springfield, 50210. She is 25 years old and works as manager at McDonald's.
    McDonald's in New York is located at 456 Elm St, NYC, 12345. Her favourite books are "The Lord
    of the Rings" and "The Hobbit" by JRR Tolkien.
    TEXT;

print("INPUT:\n$text\n\n");
$person = StructuredOutput::using('openai')->with(
    messages: $text,
    responseModel: $structure,
)->get();

print("OUTPUT:\n");
$personData = match (true) {
    $person instanceof Structure => $person->toArray(),
    is_array($person) => $person,
    default => (array) $person,
};
print("Name: " . ($personData['name'] ?? '') . "\n");
print("Age: " . ($personData['age'] ?? '') . "\n");
print("Gender: " . ($personData['gender'] ?? '') . "\n");
print("Address / city: " . ($personData['address']['city'] ?? '') . "\n");
print("Address / ZIP: " . ($personData['address']['zip'] ?? '') . "\n");
print("Role: " . ($personData['role'] ?? '') . "\n");
print("Favourite books:\n");
foreach (($personData['favourite_books'] ?? []) as $bookData) {
    if (!is_array($bookData)) {
        continue;
    }
    print("  - " . ($bookData['title'] ?? '') . " by " . ($bookData['author'] ?? '') . "\n");
}

assert(($personData['name'] ?? '') === 'Jane Doe');
assert(($personData['age'] ?? 0) === 25);
assert(!empty($personData['address']['city']));
assert(!empty($personData['address']['zip']));
assert(($personData['role'] ?? '') === 'manager');
assert(!empty($personData['favourite_books']));
?>
```
