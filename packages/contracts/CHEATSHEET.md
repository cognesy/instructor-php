# Instructor Contracts

Shared contracts (interfaces) and value objects for the Instructor PHP library.

This package holds the small, dependency-light abstractions that multiple Instructor
packages depend on — validation, transformation, and deserialization contracts plus
their associated value objects — so that higher-level packages can share them without
creating circular dependencies between each other.

It depends only on `cognesy/instructor-utils`.

## Contents

- `Cognesy\Instructor\Validation\` — `ValidationResult`, `ValidationError`, and the
  `CanValidateSelf` / `CanValidateObject` / `CanValidateValue` contracts.
- `Cognesy\Instructor\Transformation\` — the `CanTransformSelf` / `CanTransformData` contracts.
- `Cognesy\Instructor\Deserialization\` — the `CanDeserializeClass` / `CanDeserializeSelf` contracts.

## License

MIT
