# Schema2 Overview

`packages/schema2` is the TypeInfo-first reimplementation of `Cognesy\\Schema`.

Current implementation status:
- Essential `Cognesy\\Schema` contracts are available from `packages/schema2/src`.
- Type resolution uses TypeInfo-native adapter path (Symfony 6 compatibility removed).
- Descriptor model and descriptor-based JSON parser/renderer are available internally.
- Baseline fixtures are tracked under `packages/schema2/tests/Baseline`.

## Quality Gate

Run focused schema2 verification:

```bash
vendor/bin/pest packages/schema2/tests/Unit --compact
php packages/schema2/tools/baseline/generate.php --check
```

Run cross-package regression checks when validating integration:

```bash
vendor/bin/pest packages/instructor/tests/Regression/TransitiveObjectDefsToolCallSchemaTest.php
vendor/bin/pest packages/instructor/tests/Regression/StructuredOutputSchemaRendererRefsIntegrationTest.php
```
