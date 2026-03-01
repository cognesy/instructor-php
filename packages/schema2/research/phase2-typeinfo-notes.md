# Phase 2 Notes - TypeInfo Native Resolution

## Implemented
- Removed Symfony 6 compatibility branch from schema2 type resolution.
- Removed legacy adapter classes from schema2 runtime.
- `Reflection\PropertyInfo` now resolves TypeInfo directly via a static `PropertyInfoExtractor` instance.
- Type resolution flow stays TypeInfo-first without intermediary adapter/service layers.

## Validation
- Unit tests added in `packages/schema2/tests/Unit/TypeInfoNativeResolutionTest.php` for:
  - nullable collection resolution
  - iterable collection resolution
  - nested-array normalization behavior
  - scalar union policy (`int|float`, `int|string`)
  - non-scalar union rejection policy
  - absence of legacy V6 adapter class

## Command used
```bash
vendor/bin/pest packages/schema2/tests/Unit/TypeInfoNativeResolutionTest.php --compact
```

Result:
- `6 passed (9 assertions)`
