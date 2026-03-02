# Schema Package Cheatsheet

## Purpose

`Cognesy\Schema` maps PHP types/classes to schema objects and renders/parses JSON Schema.

Primary classes:
- `Cognesy\Schema\SchemaBuilder`
- `Cognesy\Schema\SchemaFactory`
- `Cognesy\Schema\CallableSchemaFactory`
- `Cognesy\Schema\TypeInfo`
- `Cognesy\Schema\JsonSchemaRenderer`
- `Cognesy\Schema\JsonSchemaParser`

---

## Quick Start

```php
<?php
use Cognesy\Schema\SchemaBuilder;

$schema = SchemaBuilder::define('user')
    ->string('name')
    ->int('age', required: false)
    ->schema();
```

---

## SchemaBuilder API

Create:

- `SchemaBuilder::define(string $name, string $description = ''): SchemaBuilder`
- `SchemaBuilder::fromSchema(Schema $schema): SchemaBuilder`

Property methods:

- `withProperty(string $name, Schema $schema, bool $required = true): SchemaBuilder`
- `string()`, `int()`, `float()`, `bool()`, `array()`
- `enum()`, `option()`, `object()`, `collection()`, `shape()`

Build:

- `schema(): ObjectSchema`
- `build(): ObjectSchema`

Implementation note:
- `SchemaBuilder` reuses one `SchemaFactory` instance per builder lifecycle.

---

## SchemaFactory API

### Create factory

```php
<?php
use Cognesy\Schema\SchemaFactory;

$factory = SchemaFactory::default();
```

### Main schema entrypoint

`$factory->schema(mixed $anyType): Schema`

Supported inputs:
- `Schema`
- `Symfony\Component\TypeInfo\Type`
- class-string (for example `User::class`)
- object instance (mapped by runtime class)
- `CanProvideSchema`
- `CanProvideJsonSchema`

### Primitive helpers

```php
<?php
$name = $factory->string('name', 'User name');
$age = $factory->int('age');
$rating = $factory->float('rating');
$active = $factory->bool('active');
$meta = $factory->array('meta');
```

### Object / enum / collection

```php
<?php
$user = $factory->object(User::class, 'user');
$status = $factory->enum(Status::class, 'status');
$tags = $factory->collection('string', 'tags');
```

Notes:
- enum rendering preserves backing values exactly (`string` and `int`)
- int-backed enums render as JSON Schema `type: integer` with integer `enum` values

### From Symfony TypeInfo `Type`

```php
<?php
use Symfony\Component\TypeInfo\Type;

$profile = $factory->fromType(Type::object(Profile::class), 'profile');
$ids = $factory->fromType(Type::list(Type::int()), 'ids');
```

### Metadata override

```php
<?php
use Cognesy\Schema\SchemaFactory;

$named = SchemaFactory::withMetadata($schema, name: 'payload', description: 'Payload data');
```

### Parse / render JSON Schema

```php
<?php
use Cognesy\Utils\JsonSchema\JsonSchema;

$renderer = $factory->schemaRenderer();
$parser = $factory->schemaParser();

$json = $renderer->render($schema);              // JsonSchema object
$array = $factory->toJsonSchema($schema);        // array<string,mixed>

$parsed = $parser->parse(JsonSchema::fromArray($array)); // Schema
```

---

## Schema Data Objects

Base type:
- `Cognesy\Schema\Data\Schema`

Specialized types:
- `ObjectSchema`
- `ArrayShapeSchema`
- `CollectionSchema`
- `ArraySchema`
- `ScalarSchema`
- `EnumSchema`
- `ObjectRefSchema`

Common methods on `Schema`:
- `name()`
- `description()`
- `type()`
- `isNullable()`
- `hasDefaultValue()`
- `defaultValue()`
- `isScalar()`
- `isObject()`
- `isEnum()`
- `isArray()`
- `hasProperties()`
- `getPropertyNames()`
- `getPropertySchemas()`
- `getPropertySchema(string $name)`
- `hasProperty(string $name)`
- `toArray()`

`ObjectSchema` and `ArrayShapeSchema` include:
- `public array $properties`
- `public array $required`

`CollectionSchema` includes:
- `public Schema $nestedItemSchema`

Default/nullability behavior:
- reflection extraction maps constructor/property/setter defaults to schema node metadata
- JSON bridge preserves `nullable` and `default` in both directions
- `hasDefaultValue()` distinguishes "no default" from `defaultValue() === null`

---

## TypeInfo Helpers

Class: `Cognesy\Schema\TypeInfo`

### Build and normalize
- `fromTypeName(?string $typeName): Type`
- `fromValue(mixed $value): Type`
- `fromJsonSchema(JsonSchema $json): Type`
- `normalize(Type $type, bool $throwOnUnsupportedUnion = false): Type`

### Classification
- `isScalar(Type $type): bool`
- `isBool(Type $type): bool`
- `isMixed(Type $type): bool`
- `isEnum(Type $type): bool`
- `isObject(Type $type): bool`
- `isArray(Type $type): bool`
- `isCollection(Type $type): bool`

### Details
- `collectionValueType(Type $type): ?Type`
- `className(Type $type): ?string`
- `enumValues(Type $type): array`
- `enumBackingType(Type $type): ?string`
- `shortName(Type $type): string`
- `toJsonType(Type $type): JsonSchemaType`
- `isDateTimeClass(Type $type): bool`
- `cacheKey(Type $type, ?array $enumValues = null): string`

---

## Reflection Helpers

### ClassInfo

`Cognesy\Schema\Reflection\ClassInfo`

```php
<?php
use Cognesy\Schema\Reflection\ClassInfo;

$classInfo = ClassInfo::fromString(User::class);
$properties = $classInfo->getProperties();
$required = $classInfo->getRequiredProperties();
```

Key methods:
- `getClass()`
- `getShortName()`
- `getPropertyNames()`
- `getProperties()`
- `getProperty(string $name)`
- `getPropertyType(string $property)`
- `hasProperty(string $property)`
- `isPublic(string $property)`
- `isReadOnly(string $property)`
- `isNullable(?string $property = null)`
- `getClassDescription()`
- `getPropertyDescription(string $property)`
- `getRequiredProperties()`
- `isEnum()`
- `isBacked()`
- `enumBackingType()`
- `implementsInterface(string $interface)`
- `getFilteredPropertyNames(array $filters)`
- `getFilteredProperties(array $filters)`

### PropertyInfo

`Cognesy\Schema\Reflection\PropertyInfo`

```php
<?php
use Cognesy\Schema\Reflection\PropertyInfo;

$property = PropertyInfo::fromName(User::class, 'email');
$type = $property->getType();
```

Key methods:
- `fromName(string $class, string $property)`
- `fromReflection(ReflectionProperty $reflection)`
- `getName()`
- `getType()`
- `getDescription()`
- `isNullable()`
- `isPublic()`
- `isReadOnly()`
- `isStatic()`
- `isDeserializable()`
- `isRequired()`
- `hasAttribute(string $attributeClass)`
- `getAttributeValues(string $attributeClass, string $attributeProperty)`
- `getClass()`

### FunctionInfo

`Cognesy\Schema\Reflection\FunctionInfo`

```php
<?php
use Cognesy\Schema\Reflection\FunctionInfo;

$info = FunctionInfo::fromFunctionName('trim');
$params = $info->getParameters();
```

Key methods:
- `fromClosure(Closure $closure)`
- `fromFunctionName(string $name)`
- `fromMethodName(string $class, string $name)`
- `getName()`
- `getShortName()`
- `isClassMethod()`
- `getDescription()`
- `getParameterDescription(string $argument)`
- `hasParameter(string $name)`
- `isNullable(string $name)`
- `isOptional(string $name)`
- `isVariadic(string $name)`
- `hasDefaultValue(string $name)`
- `getDefaultValue(string $name)`
- `getParameters()`

---

## Contracts

- `CanProvideSchema::toSchema(): Schema`
- `CanRenderJsonSchema::render(Schema $schema, ?callable $onObjectRef = null): JsonSchema`
- `CanParseJsonSchema::parse(JsonSchema $jsonSchema): Schema`

---

## Package checks

```bash
./vendor/bin/pest packages/schema/tests --compact
./vendor/bin/phpstan analyse packages/schema/src
./vendor/bin/psalm --config=packages/schema/psalm.xml
```
