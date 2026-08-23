# `Embeddings/` — why it mirrors `Inference/` without sharing code

`Embeddings/` and `Inference/` have near-identical directory layouts (`Config/`,
`Contracts/`, `Creation/`, `Data/`, `Drivers/`, `Events/`, `Pricing/`, `Traits/`). This looks
like duplication and has been filed as such at least once. It is not.

Measured: `Embeddings/` is **52 files / ~2,232 LOC**; `Inference/` is **146 files /
~12,167 LOC**. Embeddings is ~18% the size because it does ~18% as much: **no streaming, no
tool calls, no messages, no reasoning, no capability negotiation.**

## What the parallel pairs actually contain

| Pair | Relationship |
|---|---|
| `Pricing/FlatRateCostCalculator` | **Correctly different.** Inference bills input + output + cacheRead + cacheWrite + reasoning. Embeddings bills **input only** — which is what embedding APIs charge. Merging them would be a bug. |
| `Config/*RetryPolicy` | **Subset, not copy.** Inference adds length-recovery (`lengthRecovery`, `lengthMaxAttempts`, `lengthContinuePrompt`, `maxTokensIncrement`, the `LengthRecovery` enum) and default retry status/exception lists. Both **already share** `RetryPolicyInvariants`. |
| `Contracts/CanMapRequestBody` | Identical modulo the domain name. One method. |
| `Contracts/CanMapUsage` | Identical modulo the domain name. One method. |
| `Contracts/` overall | 10 vs 14, and mostly domain-specific. `CanHandleVectorization` and `CanCreateEmbeddings` have no Inference analogue; `CanMapMessages`, `CanTranslateInferenceResponse` and `CanDescribeCapabilities` have no Embeddings analogue. |
| `Events/` | 5 vs 15. Embeddings has no streaming, so `PartialInferenceDeltaCreated`, `StreamEventParsed`, `StreamFirstChunkReceived` and friends are correctly absent. |

## The decision on the two identical interfaces

`CanMapRequestBody` and `CanMapUsage` really are duplicated — about 8 lines in total.

**They are deliberately left separate.** They are one-method interfaces in two bounded
contexts. Merging them would couple `Inference/` and `Embeddings/` so that neither can change
its signature without the other, to save eight lines. Not every duplication is a defect.

If a future change makes the two domains genuinely co-vary, revisit this. Until then, the
structural parallel is the point: the two subtrees are *readable in the same way* without
being *bound to each other*.
