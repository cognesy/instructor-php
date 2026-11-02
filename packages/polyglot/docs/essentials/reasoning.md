---
title: Reasoning
description: Select model-aware reasoning modes, effort levels, and token budgets through one portable API.
---

Reasoning controls are model and protocol capabilities, not one universal
provider option. Polyglot represents caller intent with `ReasoningSelection`
and translates it only at the provider request-body boundary.

## Named Effort

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

$message = Inference::using('openai')
    ->withModel('gpt-5.6')
    ->withMessages('Check whether this argument is logically sound.')
    ->withReasoning(
        ReasoningSelection::effort(ReasoningEffort::High),
    )
    ->get();
```

The portable effort vocabulary is `minimal`, `low`, `medium`, `high`,
`xhigh`, and `max`. This vocabulary is a superset, not a promise that every
model accepts every value. Polyglot checks the selected model's curated
capability profile and rejects unsupported or lossy mappings before sending
the request.

## Other Selection Kinds

Providers expose several distinct reasoning controls, so the unified type is a
tagged selection rather than a nullable effort string:

```php
ReasoningSelection::providerDefault();
ReasoningSelection::disabled();
ReasoningSelection::enabled();
ReasoningSelection::effort(ReasoningEffort::Medium);
ReasoningSelection::budget(4096);
ReasoningSelection::adaptive();
ReasoningSelection::adaptive(ReasoningEffort::High);
```

`providerDefault()` is non-invasive: Polyglot emits no reasoning field, so
existing raw options and provider defaults remain unchanged. An explicit typed
selection conflicts with raw keys such as `reasoning_effort`, `reasoning`, or
`thinking`; remove the raw key before using `withReasoning()`.

## Function Tools on OpenAI Chat Completions

OpenAI Chat Completions rejects function tools on reasoning-by-default models
unless `reasoning_effort` is `none`, while non-reasoning models such as
`gpt-4o-mini` reject the field entirely. The model catalog states both cases as
capability facts:

- `toolsWithReasoning: unsupported` means function tools cannot be combined with
  enabled reasoning on that exact offering (`openai` records for `gpt-6-*`,
  `gpt-6.1-*`, `gpt-5.6*`). The `openai-responses` records mark it `supported`.
- `reasoning: {selections: [], default: disabled}` means the model takes no
  reasoning control at all (for example `openai/gpt-4o-mini`), so typed reasoning
  selections are rejected before the request is sent.

For tool calls with `providerDefault()` reasoning and no raw `reasoning_effort`,
the `openai` driver sends `reasoning_effort: none` only when the exact model
record (attached profile, or the bundled record for that model) has
`toolsWithReasoning: unsupported` and reasoning can be disabled. Unknown models are
sent unchanged.

With a profile attached, preflight rejects tool requests that would keep reasoning
enabled on an offering with `toolsWithReasoning: unsupported`: an explicit
reasoning selection, or default reasoning on a model that cannot disable it
(`gpt-6-astra`, `gpt-6.1-sol`). Use the `openai-responses` driver for those.

## Curated Capability Matrix

Capabilities are resolved for the model and protocol together. Unknown model
IDs fail closed for explicit reasoning selections; Polyglot does not infer
support from a provider name or a model-name substring.

| Bundled route | Exact bundled offering(s) | Portable selection |
| --- | --- | --- |
| OpenAI Chat Completions | `openai` / `gpt-5.6` | disabled; low, medium, high, xhigh |
| OpenAI Chat Completions | `openai` / `gpt-6-luna`, `gpt-6-sol`, `gpt-6.1-terra`, `gpt-5.6-luna`, `gpt-5.6-sol`, `gpt-5.6-terra` | disabled; low, medium, high, xhigh, max |
| OpenAI Chat Completions | `openai` / `gpt-6-astra`, `gpt-6.1-sol` | low, medium, high, xhigh, max |
| OpenAI Responses | `openai-responses` / `gpt-5.6` | disabled; low, medium, high, xhigh, max |
| OpenAI Responses | `openai-responses` / same offerings as Chat Completions above | as listed for Chat Completions |
| Anthropic | `claude-opus-4-6`, `claude-sonnet-4-6` | disabled, adaptive, budget; low, medium, high, max |
| DeepSeek | `deepseek-v4-flash`, `deepseek-v4-flash-vision-exp`, `deepseek-v4-pro` | disabled; low, high, max |
| Gemini native | `gemini-2.5-flash-lite` | disabled, adaptive, budget |
| Gemini native | `gemini-3.1-pro-preview` | low, high; reasoning is mandatory |
| Gemini OpenAI protocol | `gemini-3.1-pro-preview` | low, high; reasoning is mandatory |
| GLM | `glm-4.7` | disabled, enabled, adaptive |
| Qwen | `qwen3.8-max` | disabled, enabled, adaptive, budget; low, medium, xhigh |
| Cohere | `command-a-reasoning-08-2025` | disabled, enabled, adaptive, budget |
| Mistral | `magistral-medium-latest` | disabled; high |
| Moonshot | `kimi-k2.5`, `kimi-k2.6` | disabled, enabled, adaptive |
| xAI | `grok-4.6` | low, medium, high, xhigh; reasoning is mandatory |
| OpenRouter | `openai/gpt-oss-120b` | all selection kinds and named efforts |

DeepSeek documents `medium` and `xhigh` as aliases that behave like `high`.
Polyglot records those as lossy mappings and does not accept them by default;
callers can select `high` explicitly and retain truthful effective intent.

## Capability Inspection

Inspect reasoning metadata on the exact `(driver, wire model)` profile:

```php
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

$reasoning = ModelCatalog::discover()
    ->find('qwen', 'qwen3.8-max')
    ->capabilities
    ->reasoning;

$reasoning->known;
$reasoning->selectionKinds->all();
$reasoning->effortMappings->all();
$reasoning->budgetRange;
$reasoning->defaultBehavior;
$reasoning->reasoningContentVisible;
$reasoning->reasoningTokensVisible;
$reasoning->supports(
    ReasoningSelection::effort(ReasoningEffort::Medium),
);
```

A missing exact pair returns an unknown profile, so `known` is `false`. That is
not a local rejection: explicit reasoning can proceed when the driver can render
it without model-specific data. A wire format that needs an exact effort mapping
fails with a missing-translation-fact error unless the caller supplies the facts
through an explicitly composed catalog or directly on the request with
`withReasoningCapabilities()`. Polyglot never inherits them from a provider or a
similarly named model.

Lossy effort mappings are rejected by default. Applications that accept a known
semantic change can set `allowLossyFallback: true` on `LLMConfig`; framework
connection configuration exposes `allow_lossy_fallback`. The effective request
records every accepted adjustment so it can be observed before transport or in
the `InferenceRequested` event.
