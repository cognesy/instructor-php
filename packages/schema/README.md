## Cognesy Schema

`packages/schema` provides schema mapping and JSON Schema rendering/parsing for Instructor.

### Main entry points

- `Cognesy\Schema\SchemaBuilder` - fluent builder for runtime object schemas.
- `Cognesy\Schema\SchemaFactory` - build `Schema` objects from PHP types, classes, objects, or JSON Schema providers.
- `Cognesy\Schema\CallableSchemaFactory` - build `Schema` from callable signatures.
- `Cognesy\Schema\TypeInfo` - type normalization and helpers based on Symfony TypeInfo.
- `Cognesy\Schema\JsonSchemaRenderer` - render `Schema` to JSON Schema.
- `Cognesy\Schema\JsonSchemaParser` - parse JSON Schema into `ObjectSchema`.

### Quick start

```php
<?php
use Cognesy\Schema\SchemaFactory;

$factory = SchemaFactory::default();

$schema = $factory->schema(User::class);
$jsonSchema = $factory->toJsonSchema($schema);
```

### Build schemas directly

```php
<?php
use Cognesy\Schema\SchemaBuilder;

$schema = SchemaBuilder::define('user')
    ->string('name', 'User name')
    ->int('age', required: false)
    ->collection('tags', 'string', required: false)
    ->schema();
```

### Nullable and default metadata

```php
<?php
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

$factory = SchemaFactory::default();

$nickname = $factory->propertySchema(
    type: Type::string(),
    name: 'nickname',
    description: 'Optional nickname',
    nullable: true,
    hasDefaultValue: true,
    defaultValue: null,
);
```

`nullable`, `hasDefaultValue`, and `defaultValue` are preserved when converting:
- PHP reflection -> `Schema`
- `Schema` -> JSON Schema
- JSON Schema -> `Schema`

Enum values are preserved as declared (string-backed and int-backed enums are both supported in JSON Schema output).

### Parse JSON Schema

```php
<?php
use Cognesy\Schema\JsonSchemaParser;

$parser = new JsonSchemaParser();
$objectSchema = $parser->fromJsonSchema($jsonSchemaArray);
```

### Tests

```bash
./vendor/bin/pest packages/schema/tests --compact
```
