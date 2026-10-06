---
title: Fastino GLiDE Backend
description: Answer typed System One questions with Fastino's GLiDE decision model.
---

<!-- markdownlint-disable MD013 -->

The `fastino` driver connects Polyglot Decision to Fastino's hosted
[GLiDE](https://docs.fastino.ai/inference/systemone) decision model
(`POST https://api.fastino.ai/v1/systemone`). It speaks the System One
protocol: a state plus a map of typed `noul`, `choice`, and `score` questions
in, one probability distribution per question out.

| Model | Limit | Input price |
| --- | --- | --- |
| `fastino/GLiDE` | 40,000 tokens per state plus one question | $0.15 per M tokens |

Output tokens are reported but free. Reported input usage sums GLiDE's internal
passes across questions, so it can exceed 40,000 tokens even when every
question fits.

## Configure the driver

Set a Fastino API key:

```dotenv
FASTINO_API_KEY=your-key
```

Then select the bundled preset:

```php
use Cognesy\Polyglot\Decision\Decision;

$decision = Decision::using('fastino');
```

The preset targets `https://api.fastino.ai/v1/systemone` with `fastino/GLiDE`
and sends the key in the `X-API-Key` header. Responses report the model as
`glide`.

## Timeouts and warming retries

Fastino recommends a read timeout of at least 300 seconds, and a cold model
answers `425` until it has warmed up (about 60 seconds). The default HTTP
client times out after 30 seconds and Decision retries are off by default, so
configure both for Fastino:

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionRuntime;

$runtime = DecisionRuntime::fromConfig(
    config: DecisionConfig::fromPreset('fastino'),
    httpClient: (new HttpClientBuilder)
        ->withConfig(new HttpClientConfig(driver: 'symfony', requestTimeout: 300, idleTimeout: 300))
        ->create(),
);

$answers = Decision::fromRuntime($runtime)
    ->withRetryPolicy(new DecisionRetryPolicy(maxAttempts: 3, maxDelayMs: 65000))
    ->with(input: $ticket, questions: $questions)
    ->get();
```

A `425` without a `Retry-After` header waits 60 seconds; a header is honored up
to `maxDelayMs`. `429` and `503` retry with the policy's normal backoff. With
the values above, a call can take up to about 17 minutes in the worst case
(3 × 300 s plus 2 × 65 s), so pick smaller values for interactive paths.

## Reading GLiDE answers

GLiDE defines confidence differently from the Polyglot answer objects:

| Answer | Polyglot accessor | GLiDE meaning |
| --- | --- | --- |
| Noul | `confidence()` is `max(p, 1 − p)` | `abs(2p − 1)`, which is not exposed |
| Choice, Score | `confidence()` is GLiDE's value | top-1 minus top-2 probability |

At `p = 0.5` the Noul accessor returns 0.5 while GLiDE's confidence is 0; at
`p = 0.8` they are 0.8 and 0.6. To gate on GLiDE's measure, derive it and
check the direction separately:

```php
$refund = $answers->noul('refund');
$approve = $refund->probability() >= 0.5
    && abs(2 * $refund->probability() - 1) >= 0.6;
```

`ScoreAnswer::value()` is GLiDE's probability-weighted `expected_level`. The
most likely level can be read from `probabilities()`. When two levels tie, the
level GLiDE selected is only available in the raw HTTP response. Tune all
thresholds on labeled data.

## Protocol notes

- Every question needs text instructions, and Score levels must be text
  because GLiDE echoes the legend as strings. The driver rejects other shapes
  before sending.
- Choice and Score accept up to 255 options or levels; Polyglot requires at
  least two Score levels. Choice descriptions are sent unchanged.
- One request evaluates one shared state. For reranking, every passage in the
  state is rendered into each question's prompt, so the 40,000-token limit
  applies to the whole shortlist plus one question.
- The `x-request-id` response header becomes the provider request ID.
- The adapter checks Noul confidence against `abs(2p − 1)` and the Score index
  against the most probable level, then validates distributions with a 0.001
  tolerance.

HTTP 401 maps to an authentication error, 402 and 403 to access errors, 425
and 408/5xx to retriable transient errors, 429 to rate limits (with
`Retry-After`), and other 4xx responses such as 404 and 422 to invalid
requests. A non-JSON body fails closed.

## Live smoke

```bash
POLYGLOT_FASTINO_LIVE=1 vendor/bin/pest packages/polyglot/tests/Integration/FastinoLiveTest.php
```

The test runs one mixed Noul, Choice, and Score decision through the bundled
preset with a 300-second timeout and warming retries. It needs
`FASTINO_API_KEY` in the environment or the repository `.env`.
