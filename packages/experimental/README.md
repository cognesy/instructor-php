# Overview

Experimental directory contains experimental components that are being researched (with highly unstable APIs).
These components are in various stages of development and may not be fully functional. They are not guaranteed
to work as expected and may be removed or changed at any time.

## Signature API (schema-first)

`Cognesy\Experimental\Signature` stores signatures as two schema objects:
- input schema
- output schema

Preferred constructors:
- `SignatureFactory::fromSchemas($inputSchema, $outputSchema)`
- `SignatureFactory::fromSchema($schemaWithInputsAndOutputs)`
- `SignatureFactory::fromCallable(...)`

Use `Signature::toRequestedSchema($name)` to project output schema for Instructor requests.

`fromStructure()` and `fromStructures()` remain available only as transitional adapters.
