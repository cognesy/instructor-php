# Release Example Policy

Examples are expensive and many hit real providers. Use a deterministic release policy.

## Goals

- verify actual production-level behavior with real API calls
- avoid rerunning the full example corpus unnecessarily
- rerun only failed live examples after fixes

## Default Strategy

Do not run all examples by default.

For a normal release:

1. Run the curated live smoke set once.
2. Add conditional live examples for changed surfaces.
3. Record exact example paths and outcomes in `bd` notes.
4. If any live example fails and you fix code, rerun only the failed examples plus any directly related new candidates.

Run the full example corpus only for:

- a major release
- broad runtime or provider rewrites
- documentation/example system rewrites

## Curated Live Smoke Set

These hit realistic behaviors while staying relatively small:

- `examples/A01_Basics/Basic/run.php`
- `examples/A02_Advanced/Streaming/run.php`
- `examples/B04_LLMApiSupport/OpenAI/run.php`
- `examples/D01_Agents/AgentLoopExecute/run.php`

These generally require `OPENAI_API_KEY`.

## Conditional Live Examples

Add these when relevant:

- OpenAI Responses / response-format work:
  - `examples/B04_LLMApiSupport/OpenAIResponses/run.php`
- provider abstraction or preset changes in `polyglot`:
  - only the provider examples touched by the release surface
- telemetry or logging changes:
  - `examples/A03_Troubleshooting/TelemetryLogfire/run.php`
  - `examples/A03_Troubleshooting/TelemetryLangfuse/run.php`
  - `examples/B03_LLMTroubleshooting/TelemetryLogfire/run.php`
  - `examples/B03_LLMTroubleshooting/TelemetryLangfuse/run.php`
- agent runtime changes:
  - add one `examples/D02_AgentBuilder/*/run.php` example relevant to the change
- agent-ctrl changes:
  - add one `examples/D10_AgentCtrl/*/run.php` example only if the required external toolchain and auth are configured

## Non-Live Supplementary Examples

These are useful but do not satisfy the live-production gate:

- `examples/C01_Http/HttpClient/run.php`
- other mock-driver or local-only examples

Use them when those surfaces changed, but do not count them as live smoke.

## Environment Expectations

At minimum, many live examples require:

- `OPENAI_API_KEY`

Telemetry examples also need the relevant backend env:

- Logfire: `LOGFIRE_TOKEN`, `LOGFIRE_OTLP_ENDPOINT`, `LOGFIRE_READ_TOKEN`
- Langfuse: `LANGFUSE_BASE_URL`, `LANGFUSE_PUBLIC_KEY`, `LANGFUSE_SECRET_KEY`

## Retry Policy

- First pass: run the selected example list once.
- After a fix: rerun only failed examples and direct siblings needed to confirm the fix.
- Do not rerun already-green expensive examples unless the fix plausibly affected them.
