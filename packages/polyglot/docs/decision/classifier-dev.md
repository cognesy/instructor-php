---
title: classifier.dev Backend
description: Use classifier.dev dimensions for native Choice and projected Noul and Score decisions.
---

<!-- markdownlint-disable MD013 -->

The `classifier-dev` driver connects Polyglot Decision to
[`classifier.dev`](https://classifier.dev/docs). classifier.dev has its own
dimensions API rather than the System One `state + questions` API. Polyglot
therefore uses a dedicated adapter instead of treating it as TypeSafe-compatible.

## Configure the driver

The public API permits keyless requests. Set `CLASSIFIER_API_KEY` only when the
deployment requires or benefits from bearer authentication:

```dotenv
CLASSIFIER_API_KEY=
```

Select the bundled preset:

```php
use Cognesy\Polyglot\Decision\Decision;

$decision = Decision::using('classifier-dev');
```

The preset calls `https://classifier.dev/v1/classify` with the `fast` tier.
The driver sends the Decision request ID as `Idempotency-Key`; an echoed value
becomes `DecisionResponse::providerRequestId()`.

## Semantic mapping

One Decision request becomes one classifier.dev item and one dimension per
question:

| Decision primitive | classifier.dev representation | Capability |
| --- | --- | --- |
| Choice | One dimension with the original options | Native |
| Noul | A `yes` / `no` Choice dimension | Projected |
| Score | A Choice dimension over ordered levels | Projected |

Projected means Polyglot derives a valid domain answer from classifier.dev's
complete label distribution. It does not mean classifier.dev implements native
System One Noul or Score semantics. For Score, the returned value is the
probability-weighted expected level. The original Score legend is retained.

The driver accepts only `fast`. A `smart` result can replace scores with null
after escalation, which cannot satisfy the typed probability contracts. Any
unscored or incomplete successful response fails closed; Polyglot never creates
a uniform or one-hot replacement distribution.

## State and limits

classifier.dev classifies text. Text state is sent unchanged; object and list
state are sent as compact, canonical JSON text. Structured question content is
rendered deterministically into labels and instructions, and returned wire
labels are mapped exactly back to original Choice IDs and Score levels.

The adapter checks the public provider limits before HTTP, including:

- at most 20 questions;
- 2 to 100 labels per question;
- 32,000 characters per item;
- 200 characters per rendered label;
- 4,000 characters per dimension instruction; and
- 16,000 characters across the encoded dimension definitions.

Values are never silently truncated. Rendered-label collisions are rejected.

classifier.dev reports classification counts and latency rather than token
usage, so `DecisionUsage` keeps both token counts unknown. The raw response,
including usage and rate-limit headers, remains available through
`DecisionResponse::responseData()`.

Use the [provider evaluation](evaluation) workflow to report Choice separately
from projected Noul and Score results. Contract-valid projections are not a
quality or calibration claim.
