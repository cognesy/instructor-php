# Instructor Dynamic

Lightweight runtime structures for schema-driven inputs and outputs.

```php
use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;

$schema = SchemaBuilder::define('search_args')
    ->string('query')
    ->int('limit', required: false)
    ->schema();

$args = Structure::fromSchema($schema, ['query' => 'laravel']);

$updated = $args->set('limit', 10);

$updated->validate()->isValid(); // true
$updated->toArray();             // ['query' => 'laravel', 'limit' => 10]
```

Use this package when you need:

- a small immutable runtime record (`Structure`)
- schema-driven structures from callables/classes (`StructureFactory`)

Schema authoring lives in `Cognesy\Schema` (`SchemaBuilder`, `SchemaFactory`, `CallableSchemaFactory`).

See [CHEATSHEET.md](CHEATSHEET.md) for API details.
