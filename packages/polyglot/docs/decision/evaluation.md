---
title: Provider Evaluation
description: Compare Decision providers with labeled corpora, primitive-specific metrics, and operational evidence.
---

<!-- markdownlint-disable MD013 -->

Wire compatibility is only the first gate. A provider can return valid typed
answers and still differ materially in accuracy, calibration, latency, failure
rate, and the downstream review workload. Evaluate the exact deployed model and
configuration on independently labeled application data before using its output
in policy.

## Keep primitive semantics visible

The bundled model records declare how each primitive is produced:

| Provider | Choice | Noul | Score | Deployment condition |
| --- | --- | --- | --- | --- |
| TypeSafe | Native | Native | Native | Evaluate the returned pinned Jev version, not only the requested alias |
| classifier.dev `fast` | Native | Projected | Projected | A complete scored distribution is required |
| Jeff | Native | Native | Native | Score requires a deployment verified at `JEFF_TEMPERATURE=1` |
| Laya | Native | Native | Native | Evaluate the exact route, revision, backend, device, and precision |

Do not combine native and projected results into one headline number. An
uncatalogued route has `unknown` capabilities; that means Polyglot lacks a fact,
not that the primitive is supported or unsupported.

## Run the fixed example corpus

The repository includes a versioned 12-case support-triage corpus with one
Noul, Choice, and Score label per case. It is deliberately small and exists to
make the evaluation mechanics reproducible. Replace it with representative,
independently labeled application cases before drawing production conclusions.

```bash
php examples/B06_Decisions/ProviderEvaluation/evaluate.php typesafe
php examples/B06_Decisions/ProviderEvaluation/evaluate.php classifier-dev
php examples/B06_Decisions/ProviderEvaluation/evaluate.php jeff
php examples/B06_Decisions/ProviderEvaluation/evaluate.php laya
```

Each command uses the named bundled preset and therefore the same environment
variables as an ordinary Decision call. Self-hosted Jeff and Laya must already
be running. Jeff Score evaluations are valid only after `/stats` reports
`temperature: 1.0`; record the other service facts alongside the JSON output.

The report includes:

- Noul accuracy, Brier score, log loss, expected calibration error, and buckets;
- Choice accuracy and multiclass Brier score, log loss, and calibration;
- Score rounded-level accuracy, mean absolute error, multiclass probability
  metrics, and calibration;
- attempts, retries, typed failure categories, end-to-end latency, and available
  provider service, queue, or batcher timing;
- returned model identities rather than only requested aliases; and
- the result of an illustrative caller-owned low-confidence review policy.

Calibration confidence is derived consistently from the returned probability
distribution: `max(p, 1-p)` for Noul and the maximum class probability for
Choice and Score. Five equal-width confidence buckets feed expected calibration
error. Score accuracy rounds the expected level only for the task-correctness
view; Score MAE retains the fractional value.

## Interpret operational facts correctly

End-to-end latency includes Polyglot and transport overhead. A service or
batcher header is not interchangeable with it, and Jeff's batcher time is not a
pure queue measurement. Compare cold and warm runs separately and record the
hardware, backend, concurrency, and exact model revision.

Missing token usage is unknown, not zero. Likewise, a zero software price for a
self-hosted model is not zero operating cost: include compute, memory, storage,
deployment, observability, and operator time in the application decision.

Laya's `modelActionProbability` is a model signal, not permission. It may inform
an application-owned policy, but authorization and side effects remain outside
the model and outside `DecisionRuntime`.

## Treat reports as evidence, not guarantees

A small corpus has high sampling error and can be overfit by prompt wording.
Use versioned train/development/test boundaries, multiple runs when inference is
stochastic, enough examples per segment, and confidence intervals for release
decisions. Preserve malformed responses and provider failures as failed cases;
do not silently remove them or fabricate fallback distributions.
