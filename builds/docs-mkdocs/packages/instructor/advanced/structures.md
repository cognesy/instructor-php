## Dynamic Structures

Use dynamic structures when output shape is decided at runtime.

```php
<?php
use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;

$personSchema = SchemaBuilder::define('person')
    ->string('name')
    ->int('age', required: false)
    ->schema();

$personModel = Structure::fromSchema($personSchema);
// @doctest id="83a1"
```

## Schema Definition

Define shape with `SchemaBuilder` (not with `Structure`):

- scalars: `string()`, `int()`, `float()`, `bool()`, `array()`
- constrained values: `enum()`, `option()`
- nested values: `shape()`, `collection()`, `object()`

```php
<?php
use Cognesy\Schema\SchemaBuilder;

$schema = SchemaBuilder::define('person')
    ->string('name')
    ->shape('address', fn(SchemaBuilder $builder) => $builder
        ->string('city')
        ->string('zip', required: false)
    )
    ->schema();
// @doctest id="0c24"
```

## Extraction

```php
<?php
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\StructuredOutput;

$person = (new StructuredOutput)->with(
    messages: 'Jane Doe is 25 years old.',
    responseModel: Structure::fromSchema($schema),
)->get();

$data = $person->toArray();
// @doctest id="d081"
```

## Runtime Value API

`Structure` is immutable:

- `set()` and `withData()` return new instances
- `get()` reads value or schema default
- `validate()` checks schema compliance
