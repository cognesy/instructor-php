## Cognesy Schema

`packages/schema` provides schema mapping and JSON Schema rendering/parsing for Instructor.

### Main entry points

- `Cognesy\Schema\SchemaFactory` - build `Schema` objects from PHP types, classes, objects, or JSON Schema providers.
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
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

$factory = SchemaFactory::default();

$name = $factory->string('name', 'User name');
$age = $factory->int('age');
$tags = $factory->collection('string', 'tags');
$profile = $factory->fromType(Type::object(Profile::class), 'profile');
```

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
