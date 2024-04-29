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

$structure = Structure::define('person', [
    Field::string('name','Name of the person'),
    Field::int('age', 'Age of the person')->validIf(
        fn($value) => $value > 0, "Age has to be positive number"
    ),
    Field::structure('address', [
        Field::string('street', 'Street name')->optional(),
        Field::string('city', 'City name'),
        Field::string('zip', 'Zip code')->optional(),
    ], 'Address of the person'),
    Field::enum('role', Role::class, 'Role of the person'),
], 'A person object');

$text = <<<TEXT
    Jane Doe lives in Springfield. She is 25 years old and works as a line worker.
    McDonald's in Ney York is located at 456 Elm St, NYC, 12345.
    TEXT;

print("INPUT:\n$text\n\n");

$person = (new Instructor)->respond(
    messages: $text,
    responseModel: $structure,
);

print("OUTPUT:\n");
dump($person);
?>
```
