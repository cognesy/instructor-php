# Structures

Structures allow dynamically define the shape of data to be extracted
by LLM. Classes may not be the best fit for this purpose, as they declaring
them at runtime is not possible.

With structures, you can define custom data shapes dynamically, at runtime
to specify the information you need LLM to infer from the provided text or
chat messages.



## Defining a shape of data

Use `Structure::define()` to define the structure and pass it to Instructor
as response model.

If `Structure` instance has been provided as a response model, Instructor
returns an array in the shape you defined.

`Structure::define()` accepts array of `Field` objects.

Let's first define the structure, which is a shape of the data we want to
extract from the message.

```php
<?php
use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;

enum Role : string {
    case Manager = 'manager';
    case Line = 'line';
}

$structure = Structure::define('person', [
    Field::string('name'),
    Field::int('age'),
    Field::enum('role', Role::class),
]);
?>
```

Following types of fields are currently supported:

- `Field::bool()` - boolean value
- `Field::int()` - int value
- `Field::string()` - string value
- `Field::float()` - float value
- `Field::enum()` - enum value
- `Field::structure()` - for nesting structures


## Optional fields

Fields can be marked as optional with `$field->optional()`.  By default, all
defined fields are required.

```php
<?php
$structure = Structure::define('person', [
    //...
    Field::int('age')->optional(),
    //...
]);
?>
```


## Descriptions for guiding LLM inference

Instructor includes field descriptions in the content of instructions for LLM, so you
can use them to provide explanations, detailed specifications or requirements for each field.

You can also provide extra inference instructions for LLM at the structure level with `$structure->description(string $description)`

```php
<?php
$structure = Structure::define('person', [
    Field::string('name', 'Name of the person'),
    Field::int('age', 'Age of the person')->optional(),
    Field::enum('role', Role::class, 'Role of the person'),
], 'A person object');
?>
```

## Nesting structures

You can use `Field::structure()` to nest structures in case you want to define
more complex data shapes.

```php
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
```

## Validation of structure data

Instructor supports validation of structures.

You can define field validator with:

 - `$field->validator(callable $validator)` - $validator has to return an instance of `ValidationResult`
 - `$field->validIf(callable $condition, string $message)` - $condition has to return false if validation has not succeeded, $message with be provided to LLM as explanation for self-correction of the next extraction attempt

Let's add a simple field validation to the example above: 

```php
<?php
$structure = Structure::define('person', [
    // ...
    Field::int('age', 'Age of the person')->validIf(
        fn($value) => $value > 0, "Age has to be positive number"
    ),
    // ...
], 'A person object');
?>
```

## Extracting data

Now, let's extract the data from the message.

```php
<?php
use Cognesy\Instructor\Instructor;

$text = <<<TEXT
    Jane Doe lives in Springfield. She is 25 years old and works as a line worker. 
    McDonald's in Ney York is located at 456 Elm St, NYC, 12345.
    TEXT;

$person = (new Instructor)->respond(
    messages: $text,
    responseModel: $structure,
);

dump($person);
// array [
//   "name" => "Jane Doe"
//   "age" => 25
//   "address" => array [
//     "city" => "Springfield"
//   ]
//   "role" => "line"
// ]
?>
```
