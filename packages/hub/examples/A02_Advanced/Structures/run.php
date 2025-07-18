---
title: 'Structures'
docname: 'structures'
---

## Overview

Structures allow dynamically define the shape of data to be extracted
by LLM, e.g. during runtime.

Use `Structure::define()` to define the structure and pass it to Instructor
as response model.

If `Structure` instance has been provided as a response model, Instructor
returns an array in the shape you defined.

See more: [Structures](../../structures.md)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\StructuredOutput;

enum Role : string {
    case Manager = 'manager';
    case Line = 'line';
}

$structure = Structure::define('person', [
    Field::string('name','Name of the person'),
    Field::int('age', 'Age of the person')->validIf(
        fn($value) => $value > 0, "Age has to be positive number"
    ),
    Field::option('gender', ['male', 'female'], 'Gender of the person')->optional(),
    Field::structure('address', [
        Field::string('street', 'Street name')->optional(),
        Field::string('city', 'City name'),
        Field::string('zip', 'Zip code')->optional(),
    ], 'Address of the person'),
    Field::enum('role', Role::class, 'Role of the person'),
    Field::collection('favourite_books', Structure::define('book', [
            Field::string('author', 'Book author')->optional(),
            Field::string('title', 'Book title'),
        ], 'Favorite book data'),
    'Favorite books of the person'),
], 'A person object');

$text = <<<TEXT
    Jane Doe lives in Springfield, 50210. She is 25 years old and works as manager at McDonald's.
    McDonald's in Ney York is located at 456 Elm St, NYC, 12345. Her favourite books are "The Lord
    of the Rings" and "The Hobbit" by JRR Tolkien.
    TEXT;

print("INPUT:\n$text\n\n");
$person = (new StructuredOutput)->with(
    messages: $text,
    responseModel: $structure,
)->get();

print("OUTPUT:\n");
print("Name: " . $person->name . "\n");
print("Age: " . $person->age . "\n");
print("Gender: " . $person->gender . "\n");
print("Address / city: " . $person->address->city . "\n");
print("Address / ZIP: " . $person->address->zip . "\n");
print("Role: " . $person->role->value . "\n");
print("Favourite books:\n");
foreach ($person->favourite_books as $book) {
    print("  - " . $book->title . " by " . $book->author . "\n");
}
?>
```
