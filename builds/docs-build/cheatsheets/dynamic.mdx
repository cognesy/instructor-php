---
title: Dynamic
description: Runtime data structures — schema-driven immutable objects with validation
package: dynamic
---

# Dynamic Cheat Sheet

## Mental Model

- `Structure` is runtime state: `{ data: array, schema: Schema }`
- Shape is always defined by `Schema` (use `SchemaBuilder` / `SchemaFactory`)
- `Structure` is immutable (`set()` / `withData()` return new instances)

## Quick Start

```php
use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;

$schema = SchemaBuilder::define('payload')
    ->string('name')
    ->bool('active', required: false)
    ->schema();

$payload = Structure::fromSchema($schema, ['name' => 'Ada']);
$payload = $payload->set('active', true);
$data = $payload->toArray();
```

## Structure API

Create:

- `Structure::fromSchema(Schema $schema, array $data = []): Structure`

Read:

- `schema(): Schema`
- `name(): string`
- `description(): string`
- `toSchema(): Schema`
- `data(): array`
- `toArray(): array`
- `toJsonSchema(): array`
- `has(string $name): bool`
- `get(string $name): mixed`

Magic access:

- `isset($structure->field)` is true only when runtime data contains a non-null value or the schema provides a non-null default

Mutate (immutable):

- `withData(array $data): Structure`
- `set(string $name, mixed $value): Structure`
- `clone(): Structure`
- `fromArray(array $data): static`

Validation / transform:

- `validate(): ValidationResult`
- `normalizeRecord(array $values): array`
- `transform(): mixed`
- `withValidation(callable $validator): Structure` — compatibility no-op, returns `$this`

## StructureFactory API

- `fromCallable(callable $callable, ?string $name = null, ?string $description = null): Structure`
- `fromFunctionName(string $function, ?string $name = null, ?string $description = null): Structure`
- `fromMethodName(string $class, string $method, ?string $name = null, ?string $description = null): Structure`
- `fromClass(string $class, ?string $name = null, ?string $description = null): Structure`
- `fromSchema(string $name, Schema $schema, string $description = ''): Structure`
- `fromJsonSchema(array $jsonSchema): Structure`
- `fromArrayKeyValues(string $name, array $data, string $description = ''): Structure`
- `fromString(string $name, string $typeString, string $description = ''): Structure`

`fromString()` is a convenience shape parser for compact field lists. It supports top-level comma-separated fields, nested type strings like `array{name:string}`, and field descriptions in trailing parentheses.

## Schema Authoring

Use `Cognesy\Schema` for shape definition:

- `SchemaBuilder` for fluent authoring
- `SchemaFactory` for type/class driven schemas
- `CallableSchemaFactory` for function/method signatures
