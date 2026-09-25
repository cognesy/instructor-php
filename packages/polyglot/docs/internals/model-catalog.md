---
title: Model Catalog
description: Exact, request-scoped model facts without provider guessing.
---

## Domain-owned catalogs

Inference, embeddings, and structured decisions use separate model types because
their useful facts differ:

| Domain | Catalog | Model facts |
| --- | --- | --- |
| Inference | `Inference\Models\ModelCatalog` | support, limits, modalities, capabilities, reasoning |
| Embeddings | `Embeddings\Models\ModelCatalog` | input limits, default dimensions, optional input pricing |
| Decision | `Decision\Models\ModelCatalog` | request budgets, primitive semantics, and optional input/output pricing |

They share exact `(driver, wire model)` lookup semantics, not a common capability
object. This prevents an embedding dimension or Jev state budget from being
misrepresented as an inference capability.

## Inference identity and source

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

## Optional request-scoped resolution

Ordinary inference does not discover, load, or consult a model catalog. Compose the runtime once
per service or worker and reuse it across isolated inference objects:

```php
use Cognesy\Polyglot\Inference\Creation\InferenceDriverRegistry;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$drivers = InferenceDriverRegistry::default();
$runtime = InferenceRuntime::fromConfig($config, drivers: $drivers);

$first = Inference::fromRuntime($runtime)->withMessages($messages)->create();
$second = Inference::fromRuntime($runtime)->withMessages($otherMessages)->create();
```

Pass a catalog only when the application wants local capability enforcement, metadata, or facts
needed by a selected processor:

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;

$catalog = ModelCatalog::discover()->overlay(
    ModelCatalog::fromPaths('/home/app/.config/instructor/models'),
);

$runtime = InferenceRuntime::fromConfig($config, models: $catalog);
```

With this explicit composition, `InferenceRuntime` resolves the exact profile after applying a
request-level model override. The transient profile travels with the in-process request so local
policy, translation, and telemetry describe the model actually executed. It is excluded from
request serialization.

Embedding and Decision runtimes use the same explicit composition with their own
catalog classes:

```php
use Cognesy\Polyglot\Decision\Models\ModelCatalog as DecisionModels;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\Models\ModelCatalog as EmbeddingModels;

$embeddingRuntime = EmbeddingsRuntime::fromConfig(
    $embeddingConfig,
    models: EmbeddingModels::discover(),
);
$decisionRuntime = DecisionRuntime::fromConfig(
    $decisionConfig,
    models: DecisionModels::discover(),
);
```

The effective request model is resolved before lookup. A request-level override
therefore changes both the wire route and attached profile. The profile is
transient and excluded from `toArray()`. Without `models:`, runtime performs no
catalog lookup or catalog-based validation.

Embedding runtime enforces a known `maxInputs` before HTTP. Unknown limits are
permissive. Token limits in both newer catalogs are inspection and application
budgeting facts; Polyglot does not estimate provider tokens or split requests
automatically.

```php
$embedding = EmbeddingModels::discover()
    ->find('openai', 'text-embedding-3-small');
$decision = DecisionModels::discover()
    ->find('typesafe', 'jev-1.13.0');

$embedding->maxInputs;                 // ?int
$embedding->defaultDimensions;         // ?int
$decision->maxRequestTokens;           // ?int
$decision->maxStateAndQuestionTokens;  // ?int
$decision->capabilities->choice;       // DecisionPrimitiveSupport
$decision->capabilities->noul;         // DecisionPrimitiveSupport
$decision->capabilities->score;        // DecisionPrimitiveSupport
```

`discover()` resolves domain-specific model directories through the same application,
monorepo, and vendor layouts as presets: `config/llm/models`,
`config/embed/models`, or `config/sdm/models`. It creates a reusable catalog
without reading any model records. `find()` reads only the exact offering's YAML
file, using the first matching directory. Application records take precedence
over packaged records. Replacements are whole records; omitted facts become
unknown rather than inheriting packaged values.

Discovered catalogs are memoized by application root. `discover($projectRoot)` selects a project
explicitly without changing the global base path; `fromPaths($projectModels, $packageModels)`
creates an independent catalog with explicit ordered roots. Each catalog caches hydrated profiles
and missing keys. Reusing it across hundreds of requests avoids repeated file
reads and hydration. Inference's richer catalog also offers explicit enumeration
through `count()`, iteration, `forDriver()`, and `toArray()`; embedding and
Decision catalogs intentionally expose exact lookup only.

Profiles are immutable. A catalog instance has no automatic reload; create a new instance to
change its configured scope. The shared config reader caches parsed source by path and mtime;
tests or development tools rewriting files within one timestamp tick can explicitly call
`Config::flushSourceCache()`. Deploy records with the application's immutable release directory,
not by rebuilding a directory active requests are reading. Runtime performs no network lookup,
model-family matching, catalog writes, database query, or background refresh.

Before a provider body is rendered, an in-process preflight step reads only facts explicitly
attached to that request. It does not make an LLM or network request. Explicitly unsupported
streaming, tools, tool choice, JSON Object, and reasoning selections are rejected. Missing or
unknown facts make no local support assertion, so custom models remain executable.

Decision preflight applies the same rule to Choice, Noul, and Score questions:
only `unsupported` rejects a request. `native` means the provider implements the
primitive directly, while `projected` means its driver maps that primitive onto a
different provider operation. These states describe semantics, not model quality,
calibration, accuracy, or fitness for an application.

A semantic change such as JSON Schema to JSON Object requires both an exact supported fallback
fact and `LLMConfig::$allowLossyFallback === true`. The default is `false`. Approved changes are
recorded on the effective request and included in the `InferenceRequested` event; adapters never
silently remove or weaken requested behavior. An adapter can still reject an operation it cannot
encode, which is a protocol limitation rather than a catalog capability decision.

## Catalog files

A runtime offering is one named YAML record. For example,
`config/llm/models/openai-compatible/acme-1.yaml`:

```yaml
schemaVersion: 1
version: project-1
profile:
  driver: openai-compatible
  model: acme-1
  status: supported
  limits:
    contextWindow: 128000
    maxOutput: 8192
  modalities:
    inputText: supported
    outputText: supported
  capabilities:
    streaming: supported
  source: project
```

Embedding and Decision records use the same strict envelope under
`config/embed/models` and `config/sdm/models`. Their `profile` fields are parsed
by `EmbeddingModel` and `DecisionModel`, respectively. Application records take
whole-record precedence over packaged records; omitted override facts stay
unknown rather than inheriting from a lower-priority file.

A Decision record can declare primitive semantics explicitly:

```yaml
schemaVersion: 1
version: project-1
profile:
  driver: classifier-dev
  model: fast
  capabilities:
    choice: native
    noul: projected
    score: projected
```

Decision primitive support accepts `native`, `projected`, `unsupported`, and
`unknown`. Unknown fields are omitted from serialization; an uncatalogued route
therefore remains executable.

The bundled Decision records cover `typesafe/{jev-1.13.0,jev-latest}`,
`classifier-dev/fast`, `jeff/gliformer-large-v1`, and
`laya/{laya,laya-multilingual,laya-typed-decisions}`. `jev-latest` is an
independently reviewed snapshot, not a dynamic alias resolver. Pin an exact
returned model or self-hosted revision when stable planning facts matter.

The path is `<encoded-driver>/<encoded-model>.yaml`, with each component encoded using
`rawurlencode()`; the special components `.` and `..` have their dots percent-encoded as well.
For example, model `openai/gpt-oss-120b` under driver `openrouter` is stored as
`openrouter/openai%2Fgpt-oss-120b.yaml`. The `profile` retains the exact unencoded identity,
which must match its path. `ModelRecordDirectory::relativePath(new ModelKey($driver, $model))`
provides the canonical relative path. Model facts are loaded literally, without environment or
secret substitution.

`schemaVersion` must be integer `1`; `version` is a non-empty data revision. Parsers reject
unknown fields, invalid status types, malformed nested records, and duplicate effort mappings.
Explicit null is permitted for numeric limits and the optional maximum reasoning budget;
other present fields must have their declared type. Missing facts remain unknown. A malformed
or unreadable selected record raises an error; it does not fall back to a lower-priority record.

Every support field is tri-state: `supported`, `unsupported`, or `unknown`. Omitted support and
numeric fields are unknown. Catalog serialization omits unknown and null facts.

Overall `status: supported` means only that the exact `(driver, wire model)` route is intentionally
maintained and the named driver is executable. It does not imply that every modality or feature is
supported. Each nested fact stands on its own; a sparse supported record is valid. A partially
supported distinction that the current schema cannot express stays `unknown`. For example, Qwen's
model record does not claim generic tool-choice support because some choices depend on whether
thinking is enabled, while drivers that cannot render any explicit choice use `unsupported`.

Packaged `source` values describe how the checked-in record was authored:

- `upstream-reviewed` means selected upstream facts were reviewed before being committed;
- `hand-authored` means the maintainer owns the exact assertions directly;
- project records choose their own source label, commonly `project`.

These labels are evidence categories, not automatic freshness guarantees. Capability evidence is
kept deliberately small and tied to a real consumer:

| Fact | Required evidence | Consumer |
| --- | --- | --- |
| overall status | maintained exact driver route and reviewed model identifier | Tell metadata and application policy |
| limits and modalities | reviewed upstream/provider facts; modalities must also have a represented message path | Tell metadata and application policy |
| streaming and tools | reviewed model claim plus driver request/response coverage | optional preflight and Tell metadata |
| tool choice and response formats | final wire-shape coverage for the asserted route; partial support remains unknown | optional preflight and Tell metadata |
| reasoning | exact hand-authored or reviewed selection/mapping data plus translation coverage | reasoning translation, optional preflight, and Tell metadata |

Generic file input is intentionally absent. A future document or PDF capability needs a concrete
message abstraction, at least one provider renderer, and final wire-payload tests before it can be
advertised.

The packaged record directory is generated offline from source and override files:

```bash
packages/polyglot/bin/instructor-catalog build
packages/polyglot/bin/instructor-catalog check
packages/polyglot/bin/instructor-catalog validate
```

These commands do not fetch provider APIs. Updating upstream facts is an explicit source-review
workflow, while application runtime reads only the selected deterministic record. `--target`
names a generated record directory. Build updates its YAML records and removes obsolete generated
records; do not store unrelated hand-authored files in that output directory.

The source and override JSON catalogs remain offline maintenance inputs. `ModelCatalog::fromArray()`
and `fromFile()` support explicit bulk data loading for tools and callers supplying in-memory
catalogs; runtime discovery never loads a monolithic `models.json` file. Bulk envelopes accept
`schemaVersion: 1` (omission also selects schema 1); generated runtime records require it explicitly.

## Maintaining the packaged catalog

Edit `resources/catalog/models-source.json` for reviewed base facts or
`resources/catalog/models-overrides.json` for hand-authored whole-record replacements. Review the
source diff directly in Git, then rebuild the deterministic runtime records:

```bash
just models-build
just models-check
just models-validate
```

All three commands are offline. Build validates both inputs before changing output, writes readable
individual records, and removes obsolete generated records. Check reports stale output without
changing it. Validate parses both inputs and verifies the complete generated record set. There is
no fetch, staging, apply, mapping, or provenance subsystem; upstream research and review happen
outside this package workflow.
