# Structures

> NOTE: Structure API is still in development and may change.

Structures allow dynamically define the shape of data to be extracted
by LLM, e.g. during runtime.

Use `Structure::define()` to define the structure and pass it to Instructor
as response model.

If `Structure` instance has been provided as a response model, Instructor
returns an array in the shape you defined.

See more: [Structures](../../structures.md)

```php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Instructor;

enum Role : string {
    case Manager = 'manager';
    case Line = 'line';
}

$structure = Structure::define([
    'name' => Field::string('Name of the person'),
    'age' => Field::int('Age of the person')->validIf(
        fn($value) => $value > 0, "Age has to be positive number"
    ),
    'address' => Field::structure(Structure::define([
        'street' => Field::string('Street name')->optional(),
        'city' => Field::string('City name'),
        'zip' => Field::string('Zip code')->optional(),
    ]), 'Address of the person'),
    'role' => Field::enum(Role::class, 'Role of the person'),
], 'Person', 'A person object');

$text = <<<TEXT
    Jane Doe lives in Springfield. She is 25 years old and works as a line worker.
    McDonald's in Ney York is located at 456 Elm St, NYC, 12345.
    TEXT;

$person = (new Instructor)->respond(
    messages: $text,
    responseModel: $structure,
);

dump($person);
?>
```
