---
title: Model Catalog
description: Exact, request-scoped model facts without provider guessing.
---

## One identity and one source

Polyglot identifies an offering by the exact pair `(driver, wire model)`. A profile owns the
offering's support status, context and output limits, modalities, capabilities, source, and
catalog version. `LLMConfig` and connection presets deliberately do not duplicate those facts.

```php
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;

$profile = ModelCatalog::discover()->find('qwen', 'qwen3.8-max');

if ($profile->capabilities->jsonSchema === SupportStatus::Supported) {
    // This exact route supports native JSON Schema.
}
```

`find()` never guesses by provider or model-family pattern. A missing pair returns a
`ModelProfile` whose status and feature facts are `unknown` and whose numeric facts are `null`.
Custom and newly released models therefore remain executable without being falsely advertised
as supported or unsupported.

Reasoning uses the same exact record. `capabilities.reasoning` declares the supported selection
kinds, exact provider effort values, effective effort, mapping quality, budget range, default
behavior, and whether reasoning content and token counts are visible. Omitting that object means
reasoning support is unknown. A `quality` value of `lossy` says the provider value would produce a
different effective effort; typed requests reject that mapping by default.

## Request-scoped resolution

`InferenceRuntime` resolves the profile after applying an `InferenceRequest` model override.
The transient profile travels with the in-process request so wire-format decisions and
telemetry describe the model actually executed. It is excluded from request serialization.

Pass a catalog explicitly when composing a runtime:

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;

$catalog = ModelCatalog::discover()->overlay(
    ModelCatalog::fromFile('/home/app/.config/instructor/models.json'),
);

$runtime = InferenceRuntime::fromConfig($config, models: $catalog);
```

`discover()` reads application `config/llm/models.json` through the same search paths as LLM
presets and overlays it on the packaged catalog. The resulting immutable catalog is memoized once
per application base path. Explicit overlays are whole-record and deterministic. Runtime performs
no network lookup, model-family matching, database query, or hidden reload.

Before a provider body is rendered, request preflight reads the attached profile. Explicitly
unsupported streaming, tools, tool choice, JSON Object, and reasoning selections are rejected.
JSON Schema degrades only when the same record explicitly supports JSON Object, and a non-text
response format is removed when the record forbids combining it with tools. Unknown ordinary
capability facts continue, which keeps private OpenAI-compatible models executable.

## Catalog files

A catalog is a versioned JSON object with a `models` list:

```json
{
  "version": "project-1",
  "models": [{
    "driver": "openai-compatible",
    "model": "acme-1",
    "status": "supported",
    "limits": {"contextWindow": 128000, "maxOutput": 8192},
    "modalities": {"inputText": "supported", "outputText": "supported"},
    "capabilities": {"streaming": "supported"},
    "source": "project"
  }]
}
```

Every support field is tri-state: `supported`, `unsupported`, or `unknown`. Omitted support and
numeric fields are unknown. Catalog serialization omits unknown and null facts.

The packaged artifact is generated offline from source and override files:

```bash
packages/polyglot/bin/instructor-catalog build
packages/polyglot/bin/instructor-catalog check
packages/polyglot/bin/instructor-catalog validate
```

These commands do not fetch provider APIs. Updating upstream facts is an explicit source-review
workflow, while application runtime reads only the committed deterministic artifact.

## Maintaining the packaged catalog

`resources/catalog/mappings.json` names every source offering exactly once. Each mapping either
points to one exact models.dev provider/model pair and lists the fields it owns, or declares the
offering `local-only` with no imports. A refresh cannot discover or add a model, substitute a
different model ID, or map broad `structured_output` data onto Polyglot capabilities.

```bash
just models-list --json
just models-refresh --revision=2026-09-10
diff -u packages/polyglot/resources/catalog/models-source.json \
  packages/polyglot/resources/catalog/staging/models-source.json
just models-apply
just models-check
just models-validate
```

Only `models-refresh` accesses the network. It records the snapshot and mapping hashes, source
revision, retrieval time, and changed exact offerings in the staging directory. `models-apply`
refuses stale or modified staging data and rejects changes outside each mapping before replacing
the source and compiled files atomically. `models-check` and `models-validate` are offline parts of
ordinary Composer QA.
