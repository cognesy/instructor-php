# Evals review - StructuredOutput and Agent focus

## StructuredOutput suite design

- Organize evals by OutputMode with shared datasets:
  - `Json`, `JsonSchema`, `Tools`, `MdJson`, `Text`.
  - Run each case across streaming and non-streaming when supported.
- Coverage buckets (each has a dataset):
  - Simple objects (flat fields, required vs optional).
  - Nested objects and arrays.
  - Numeric edge cases (int vs float, precision, rounding rules).
  - Enum and union types.
  - Unknown fields and extra keys.
  - Tool-call only responses (no content).
  - Partial and streaming deltas (multi-chunk JSON).
- Assertions should be schema-aware:
  - Accept numeric coercion where schemas allow it.
  - Allow ordering differences in arrays only when explicitly configured.
  - Provide error taxonomy for parsing, schema validation, and extraction.
- Capture pipeline artifacts:
  - raw response, message content, tool calls, JSON extraction, schema errors.

## Agent suite design

- Use deterministic tool stubs:
  - Each tool has a fixture response and input validator.
  - Record tool call sequence and payloads as artifacts.
- Focus on capability, not prose:
  - Correct tool selection.
  - Correct tool arguments.
  - Correct tool call ordering (if required).
  - Final response structure adheres to schema.
- Include failure-mode cases:
  - Tool error and recovery.
  - Invalid tool args and retry.
  - Multi-tool workflows with dependencies.

## Cross-LLM comparison strategy

- Use a capability matrix to skip unsupported modes.
- Provide a baseline set of models per provider.
- Aggregate results by:
  - model, mode, streaming flag, dataset.
- Report both pass rate and error types.

## How this fits the current codebase

- `StructuredOutput` should be the primary eval surface.
- `Agent` should be evaluated with explicit tool traces.
- `Inference` should remain a lower-priority suite, mainly for transport regressions.
