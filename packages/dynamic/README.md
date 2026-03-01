# Dynamic

Lightweight runtime structure record for schema-driven outputs.

Core API:
- `Structure` record (`schema()`, `data()`, `withData()`, `validate()`, `toArray()`)
- `StructureBuilder` fluent schema DSL
- `CallableSchemaFactory` callable/method signature -> `Schema`

Legacy-heavy internals from previous implementation were intentionally removed.
