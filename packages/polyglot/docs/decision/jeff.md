---
title: Jeff Backend
description: Connect Decision to a self-hosted Jeff System One service.
---

<!-- markdownlint-disable MD013 -->

Jeff is a distinct Decision backend for the self-hosted
[`logan-markewich/jeff`](https://github.com/logan-markewich/jeff) service. It
uses the System One request contract, while retaining Jeff-specific deployment,
rounding, error, and telemetry semantics.

## Configure the service

Set the operator-owned endpoint and optional bearer key:

```dotenv
JEFF_API_URL=http://127.0.0.1:8000
JEFF_API_KEY=
```

Then select the bundled preset:

```php
use Cognesy\Polyglot\Decision\Decision;

$decision = Decision::using('jeff');
```

The preset targets `/v1/systemone` and requests `gliformer-large-v1`. It does
not start Jeff, download model weights, or choose a compute device.

### Alternative typed-decision lab host

The separately maintained `typed-decision-lab` can host the same model through
its pinned `jeff-torch` adapter. Run that repository under Python 3.12 with its
`jeff` extra and point `JEFF_API_URL` at the lab server. The adapter composes
Jeff's engine directly; it does not proxy or launch another Jeff service.

The lab fixes Jeff's temperature to `1.0` and question isolation to `all`, and
reports both facts through `/stats`. Its `jeff-v1` response profile preserves
the TypeSafe-compatible Jeff answer shape without fabricating Laya action
signals.

## Required Score deployment setting

Run Jeff with:

```dotenv
JEFF_TEMPERATURE=1
```

Jeff's upstream default temperature scales the displayed probabilities while
leaving Score based on the raw distribution. That representation is internally
inconsistent and Polyglot rejects it. Before advertising Score support for a
deployment, verify `GET /stats` returns `temperature: 1.0`; Polyglot deliberately
does not call `/stats` for every decision.

If the deployment keeps another temperature, use Choice and Noul independently
and mark Score `unsupported` in its application model record.

## Operational behavior

- Authentication is optional. `Authorization` is omitted when `JEFF_API_KEY`
  is empty.
- Independently rounded four-decimal distributions are retained without
  fabricated normalization.
- `x-typesafe-request-id` becomes the provider request ID.
- `x-jeff-server-ms` and `x-jeff-batcher-ms` remain available on
  `DecisionResponse::responseData()`; they are not end-to-end duration.
- HTTP 429 and queue-full 529 responses are retryable. The standard
  `Retry-After` seconds header drives the current retry policy; raw millisecond
  and timing headers remain on the HTTP response.

Jeff model output is not Jev output. Capability declarations describe native
wire semantics, not calibration, accuracy, or application fitness; evaluate the
deployed model on representative data with the
[provider evaluation](evaluation) workflow.
