---
title: Laya Backend
description: Connect Decision to a separately deployed Typed Decision Lab service backed by MLX or PyTorch.
---

<!-- markdownlint-disable MD013 -->

The `laya` driver connects Polyglot Decision to a separately deployed
`typed-decision-lab` service. The Python service is maintained outside the
InstructorPHP repository and Composer packages. One PHP driver works with
either the Laya-MLX or upstream PyTorch adapter because model selection,
loading, hardware, queues, and timeouts belong to the external service.

PHP never starts Python, invokes `uv`, downloads weights, selects a device, or
falls back to another provider.

## Run the external service

Obtain the separately distributed `typed-decision-lab` repository from your
deployment source. It has its own version, Git history, `pyproject.toml`,
`uv.lock`, CLI, tests, and runtime extras. From that repository, configure one
pinned adapter, route, and checkpoint revision:

```dotenv
TYPED_DECISION_ADAPTER=laya-mlx
TYPED_DECISION_MODEL=laya-typed-decisions
TYPED_DECISION_CHECKPOINT_REVISION=<exact-40-character-checkpoint-revision>
TYPED_DECISION_HOST=127.0.0.1
TYPED_DECISION_PORT=8091
```

Run `uv run td-lab serve`. Use that repository's README for installation,
authentication, resource limits, adapter extras, and real-checkpoint smoke
commands. `/healthz` reports process liveness, while `/readyz` becomes
successful only after the checkpoint is loaded.

## Configure PHP

Point the bundled preset at the already running service:

```dotenv
LAYA_API_URL=http://127.0.0.1:8091
LAYA_API_KEY=
```

```php
use Cognesy\Polyglot\Decision\Decision;

$decision = Decision::using('laya');
```

The default preset requests the explicit `laya-typed-decisions` route. Other
bundled model records are `laya` and `laya-multilingual`. The service accepts
only its configured route; there is no automatic language or workflow routing.

Every question needs instructions because both reviewed Laya implementations
require that field. Text, object, and list state retain their System One shape
rather than being converted to classifier text.

## Model action probability

Laya returns a separate action-head probability for each answer. Polyglot keeps
it as a typed auxiliary signal:

```php
$probability = $answer->signals()->modelActionProbability();
```

This value does not replace a Choice, threshold a Noul, change a Score, or grant
permission to perform an application action. Caller-owned policy decides if and
how it is used. The primary answer and its distribution remain authoritative.

The adapter validates the only documented extension,
`action.act_probability`, and checks Laya's redundant Noul confidence against
the confidence derived from its probability. Unknown fields, incomplete
answers, and inconsistent values fail closed.

## Operational attribution

The returned model identity is `route@checkpoint-revision`, not the ambiguous
upstream string `laya-rl-agent`. `x-request-id` becomes the provider request ID.
Backend, device, precision, revision, queue, inference, and server timing facts
remain available on `DecisionResponse::responseData()` and the external service's
`/v1/models` and `/stats` endpoints.

Record those exact deployment facts when using the
[provider evaluation](evaluation) workflow. Contract parity between MLX and
PyTorch does not establish equal calibration, latency, or application quality.
