---
title: Upgrading Polyglot
description: 'Migrate applications and custom drivers across Polyglot breaking changes.'
---

## Model catalog boundary

Model limits, modalities, and capabilities now belong to the exact-offering catalog, keyed by
`(driver, wire model)`. Connection presets and `LLMConfig` contain only transport, selection,
and request-default data.

This is a breaking removal. Delete `contextLength`, `maxOutputLength`, and `pricing` from
`LLMConfig`, DSNs, framework configuration, and preset YAML. Use
`ModelCatalog::discover()->find($driver, $model)` when model facts are needed. Unknown exact
offerings return an explicit profile whose facts are unknown; Polyglot does not infer support
from a provider name or model-name regex.

Catalog records do not contain pricing. Use `InferencePricing` or `EmbeddingsPricing` explicitly
with their existing calculators when an application has sourced pricing data.

Reasoning capability data now follows the same exact lookup. Replace
`BundledInferenceDrivers::reasoningCapabilities($driver, $model)` with:

```php
$reasoning = ModelCatalog::discover()
    ->find($driver, $model)
    ->capabilities
    ->reasoning;
```

Typed reasoning is checked during request preflight. A model without its own exact record does not
inherit support from another record. Unknown ordinary feature facts still allow the request to proceed;
an explicit typed reasoning selection requires a known compatible reasoning record.

Custom drivers now implement only request execution. `CanDescribeCapabilities`,
`DriverCapabilities`, and `SpecifiedInferenceDriver` were removed. A custom spec subclass now
extends `BaseInferenceRequestDriver`:

```php
final class MyDriver extends BaseInferenceRequestDriver { /* override one method */ }
```

`LLMConfig::fromArray()` hydrates the fields owned by `LLMConfig` and ignores unrelated input
keys. There is no Agents-specific migration or compatibility alias.

### Default registry constructors

The static `BundledInferenceDrivers`, `BundledEmbeddingsDrivers`, `BundledHttpDrivers`, and
`BundledHttpPools` classes were removed. Use `InferenceDriverRegistry::default()`,
`EmbeddingsDriverRegistry::default()`, `HttpDriverRegistry::default()`, and
`HttpPoolRegistry::default()` respectively. Each memoized default is immutable; its `with*()`
methods return an isolated derived registry. Use `make()` when an empty registry is required.

## Custom Inference Drivers in v2.7

Only driver authors are affected. Preset names, `Inference`, `PendingInference`,
`InferenceStream`, `InferenceResponse`, `Embeddings`, `PendingEmbeddings`, and `LLMConfig` are
unchanged, and so is every interface under `Cognesy\Polyglot\Inference\Contracts`.

All 26 provider driver shells under `Cognesy\Polyglot\Inference\Drivers\` were removed:
`A21Driver`, `CerebrasDriver`, `DeepseekDriver`, `FireworksDriver`, `GlmDriver`, `GroqDriver`,
`InceptionDriver`, `MetaDriver`, `MinimaxiDriver`, `MistralDriver`, `OpenAIDriver`,
`OpenAICompatibleDriver`, `OpenRouterDriver`, `PerplexityDriver`, `QwenDriver`, `SambaNovaDriver`,
`XAiDriver`, `AnthropicDriver`, `AzureDriver`, `BedrockOpenAIDriver`, `CohereV2Driver`,
`GeminiDriver`, `GeminiOAIDriver`, `HuggingFaceDriver`, `OpenAIResponsesDriver`, and
`OpenResponsesDriver`. Their only content was composing collaborators, so all bundled inference
registrations — including native-protocol and bespoke-endpoint providers — are now
`InferenceDriverSpec` rows in `InferenceDriverRegistry`, all served by one
`BaseInferenceRequestDriver`. The embeddings driver of the same short name,
`Embeddings\Drivers\OpenAI\OpenAIDriver`, is untouched.

### Replacing a subclass of a bundled driver

Extend `BaseInferenceRequestDriver` and name your subclass in the spec's `driverClass`. It still
receives the five collaborators assembled for it:

```php
final class MyDriver extends BaseInferenceRequestDriver { /* override one method */ }

$registry = InferenceDriverRegistry::default()->withDriver(
    'my-provider',
    new InferenceDriverSpec(
        bodyFormat: MyBodyFormat::class,
        driverClass: MyDriver::class,
    ),
);
```

If you only changed the wire format, no subclass is needed — pass your own `bodyFormat`,
`requestAdapter`, `responseAdapter`, `usageFormat`, or `messageFormat` to the spec. Each
defaults to the OpenAI implementation.

### Replacing a `capabilities()` override

Declare the exact `(driver, model)` offering in a model catalog and inject that catalog into
`InferenceRuntime`. Capability inspection no longer constructs or interrogates a driver.

`InferenceDriverRegistry::withDriver()` still accepts a class-string or a callable, so drivers
registered that way need no change.

### Moved classes

`Cognesy\Polyglot\Inference\Contracts\MessageMapper` is now
`Cognesy\Polyglot\Inference\Drivers\MessageMapper` — it is a driver helper, not a contract.
Update the import; the class is otherwise unchanged. This one has no alias.

Five classes that neither subsystem owns moved under `Cognesy\Polyglot\Support\`:

| Old FQCN | New FQCN |
|---|---|
| `Inference\Core\SensitiveDataRedactor` | `Support\Redaction\SensitiveDataRedactor` |
| `Inference\Config\RetryBackoff` | `Support\Retry\RetryBackoff` |
| `Inference\Config\RetryJitter` | `Support\Retry\RetryJitter` |
| `Inference\Config\RetryPolicyInvariants` | `Support\Retry\RetryPolicyInvariants` |
| `Polyglot\Pricing\Cost` | `Support\Pricing\Cost` |

Polyglot 2.7 temporarily aliased the five old names. Polyglot 2.10 removes that
autoloading layer completely: update every import to the new FQCN in the table.
The old names no longer resolve.

### Removed dead classes

`Inference\Enums\InferenceContentType`, `Inference\Collections\InferenceResponseList`, and
`Embeddings\Traits\HasFinders` were deleted. None had a usage anywhere in the repository.

## Custom Embeddings Drivers in v2.7

`CanHandleVectorization` now returns the domain response directly. PHP cannot provide a
compatibility shim for this interface return-type change, so custom implementations must be
updated together with the v2.7 package upgrade.

```diff
- public function handle(EmbeddingsRequest $request): HttpResponse;
- public function fromData(array $data): ?EmbeddingsResponse;
+ public function handle(EmbeddingsRequest $request): EmbeddingsResponse;
```

Move HTTP response decoding and adaptation into `handle()`. The separate `fromData()` method
is no longer part of the driver contract. Drivers that extend `BaseEmbedDriver` inherit the
new behavior unless they override `handle()`.

## Migrating from v1 to v2

Polyglot 2.0 is centered around explicit request fields.

The main migration points are:

- remove old output mode usage
- set `responseFormat` for native JSON or JSON schema
- set `tools` and `toolChoice` for tool calling
- use `stream()->deltas()` for streaming

## Response Model

Polyglot is now explicitly the raw inference layer.

- `InferenceResponse` is the final raw provider response
- streaming yields `PartialInferenceDelta`
- structured value ownership belongs to higher-level packages such as Instructor

If older code assumed that Polyglot streaming yielded accumulated partial response snapshots, update that code to work from deltas instead.

## Before

```php
<?php

$data = $inference
    ->with(
        messages: 'Return JSON.',
        mode: $oldMode,
    )
    ->asJsonData();
```

## After

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->withMessages(Messages::fromString('Return JSON.'))
    ->withResponseFormat(new ResponseFormat(type: 'json_object'))
    ->asJsonData();
```

Markdown-JSON fallback is no longer a Polyglot concern.
Use Instructor when you need higher-level structured output strategies.

## Streaming Migration

Update old streaming code like this:

- replace partial-response iteration with `stream()->deltas()`
- assemble final raw output with `final()`
- move partial structured parsing to Instructor or your own delta accumulator
