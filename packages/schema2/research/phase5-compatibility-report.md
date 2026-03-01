# Phase 5 Report - Compatibility Hardening and Docs Alignment

## Implemented
- Added exception taxonomy for schema2 core paths:
  - `SchemaParsingException`
  - `TypeResolutionException`
  - `SchemaMappingException`
  - `ReflectionException`
- Replaced generic exceptions in core mapping/resolution flows:
  - `Factories/TypeDetailsFactory`
  - `Factories/JsonSchemaToSchema`
  - `Reflection/ClassInfo`
  - `Reflection/FunctionInfo`
- Updated `README.md` to schema2-specific quality gates and status.
- Updated `CHEATSHEET.md` adapter section to TypeInfo-only path (removed v6 compatibility narrative).

## Migration Notes (legacy internals)
- Reflection helpers now throw `Cognesy\Schema\Exceptions\ReflectionException` for domain failures:
  - missing class
  - missing property
  - invalid property filter callback
  - unsupported callable metadata
  - missing function/method parameter
- `TypeString/*` has been removed from schema2; type resolution uses TypeInfo + `TypeDetailsFactory` paths.

## Validation
Executed:
```bash
./vendor/bin/pest packages/schema2/tests --compact
composer test
```

Result:
- `schema2`: `94 passed (274 assertions)`
- `monorepo`: `3631 passed (9424 assertions)`
