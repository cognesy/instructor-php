# Schema2 Parity Matrix (Phase 0)

## Runtime-Critical Call Sites

| Package | Call Site | Capability Required | Covered by Baseline Fixture |
|---|---|---|---|
| instructor | `Creation/StructuredOutputSchemaRenderer` | Schema -> JSON Schema, refs, tool-call wrapping | `toolcall_transitive_defs`, `toolcall_defs_collision`, `schema_simple_inline` |
| instructor | `Extras/Maybe/Maybe` | SchemaFactory + JSON rendering | `schema_simple_inline` |
| dynamic | `StructureFactory::fromClass/fromSchema/fromJsonSchema` | TypeDetails resolution, JsonSchemaToSchema fidelity | `typedetails_union_policy`, `jsonschema_object_without_class`, `schema_simple_inline` |
| dynamic | `FieldFactory` / `Field` | TypeDetails factories and property schema behavior | `typedetails_union_policy` |
| addons | `FunctionCallFactory` via `Dynamic\StructureFactory` | Parameter type extraction + schema generation path | `typedetails_union_policy`, `schema_simple_inline` |

## Baseline Scenarios and Intent

| Fixture | Intent |
|---|---|
| `schema_simple_inline` | Core object rendering with nested object/enum/collection fields |
| `schema_self_ref_inline` | Self-referencing inline schema cycle cut behavior |
| `toolcall_transitive_defs` | Transitive `$defs` completeness and `$ref` integrity |
| `toolcall_defs_collision` | Distinct `$defs` keys for same-basename classes |
| `jsonschema_object_without_class` | Current behavior for object JSON schema without `x-php-class` |
| `typedetails_union_policy` | Current union policy (`int|float`, `int|string`, object unions) |

## Known Gaps (to address in later phases)
- Constructor/property metadata edge-case parity is not fully covered yet.
- Advanced JSON Schema keywords beyond current package scope are not part of Phase 0.
- Deserializer integration is intentionally outside schema2 Phase 0.
