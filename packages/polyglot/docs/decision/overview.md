---
title: Structured Decisions
description: Execute typed Noul, Choice, and Score questions through Polyglot's Decision API.
---

<!-- markdownlint-disable MD013 -->

Polyglot's `Decision` operation family evaluates application state against a closed set of typed questions. It is separate from chat inference: a decision returns `NoulAnswer`, `ChoiceAnswer`, and `ScoreAnswer` objects rather than generated text or an invented chat envelope.

TypeSafe is the first provider. The domain and runtime contracts live in Polyglot, so callers depend on the stable Decision API rather than a provider-specific SDK.

Use the rest of this section for the complete surface:

- [Question design](questions) explains `Noul`, `Choice`, `Score`, and text versus structured state.
- [Response handling](responses) covers typed answers, probability distributions, confidence, usage, and IDs.
- [Configuration and runtime](runtime) covers presets, explicit wiring, pending execution, retries, events, and custom drivers.
- [Errors and testing](errors-testing) covers provider failures, validation failures, test seams, and the opt-in live test.

## Configure TypeSafe

Set `TYPESAFE_API_KEY` in the environment. The bundled `typesafe` preset selects `https://api.typesafe.ai/v1/systemone` and the `jev-latest` model alias.

```php
use Cognesy\Polyglot\Decision\Decision;

$decision = Decision::using('typesafe');
```

Application presets use the same precedence as inference and embeddings. Put an override in `config/sdm/default.yaml` and `config/sdm/presets/<name>.yaml`. Aggregate and split Composer installs discover the bundled files from `cognesy/instructor-php` and `cognesy/instructor-polyglot`, respectively.

## Ask typed questions

```php
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

$questions = Questions::of(
    new Noul(
        id: 'billing',
        instructions: 'Is this message about a billing problem?',
        criteria: new NoulCriteria(
            true: 'A payment, charge, invoice, or refund is involved.',
            false: 'The message is unrelated to billing.',
        ),
    ),
    new Choice(
        id: 'tone',
        options: ChoiceOptions::of(
            new ChoiceOption('calm'),
            new ChoiceOption('frustrated'),
            new ChoiceOption('angry'),
        ),
        instructions: 'What is the customer tone?',
    ),
    new Score(
        id: 'urgency',
        levels: ScoreLevels::of('can wait', 'this week', 'today'),
        instructions: 'How urgently should this message be handled?',
    ),
);

$answers = Decision::using('typesafe')
    ->with(
        input: 'I was charged twice. Please fix this today.',
        questions: $questions,
    )
    ->get();

$billingProbability = $answers->noul('billing')->probability();
$tone = $answers->choice('tone')->value();
$urgency = $answers->score('urgency')->value();
```

The typed accessors reject a missing question ID or the wrong answer kind. Choice and Score answers also expose `confidence()`, `probabilities()`, and, for Score, `legend()`.

| Primitive | Meaning | Typed result |
| --- | --- | --- |
| `Noul` | Probability of yes | `NoulAnswer::probability()` in `[0, 1]` |
| `Choice` | Winner from named options | `ChoiceAnswer::value()` plus the option distribution |
| `Score` | Probability-weighted position across ordered levels | `ScoreAnswer::value()` plus levels and distribution |

## Build dynamic options

Choice IDs are application-owned strings. Build them from runtime data, then keep the returned value inside that closed set.

```php
$options = ChoiceOptions::of(...array_map(
    static fn (array $route): ChoiceOption => new ChoiceOption(
        id: $route['id'],
        description: $route['description'],
    ),
    $availableRoutes,
));

$question = new Choice(
    id: 'route',
    options: $options,
    instructions: 'Which available route best fits this request?',
);
```

Numeric-looking IDs remain strings on the wire and in typed answers.

## Use structured content

JSON input is optional. Passing a PHP string sends text state. Use `JsonContent` only when named fields or a list make the state or question definitions clearer. State, instructions, descriptions, and Score levels can all preserve JSON objects or lists.

```php
use Cognesy\Polyglot\Decision\Data\JsonContent;

$state = JsonContent::object([
    'message' => 'Please reverse the duplicate charge.',
    'account' => ['plan' => 'pro', 'charge_count' => 2],
]);

$instructions = JsonContent::object([
    'task' => 'Classify the support route.',
    'policy_version' => 3,
]);
```

`JsonContent::text()`, `object()`, and `list()` retain the root kind. Values are snapshotted, finite, and JSON-safe.

See [Question design](questions) for guidance on selecting a primitive and defining useful criteria, options, and levels.

## Use an explicit request and runtime

The facade and explicit runtime share the same execution path.

```php
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\DecisionRuntime;

$runtime = DecisionRuntime::fromConfig(
    DecisionConfig::fromPreset('typesafe'),
);

$request = new DecisionRequest(
    input: $state,
    questions: $questions,
);

$pending = $runtime->create($request); // no network I/O yet
$requestId = $pending->request()->id()->toString();
$executionId = $pending->executionId();
$response = $pending->response();       // executes once
$same = $pending->response();           // memoized; no second request
```

`DecisionResponse` exposes `answers()`, `model()`, `usage()`, `responseData()`, and `providerRequestId()`.

See [Response handling](responses) for the complete typed result API.

## Portable payload versus execution envelope

`DecisionRequest::toArray()` serializes the portable domain payload: `input`, `questions`, and an optional `model`. `fromArray()` reconstructs those typed questions.

Execution-only concerns intentionally stay outside that payload:

- API credentials and endpoint configuration belong to `DecisionConfig`.
- Retry policy belongs to the in-memory request/runtime composition.
- Request, execution, and attempt identities belong to the lifecycle.
- Telemetry correlation belongs to the execution envelope.

This separation makes a generated or persisted question payload safe to inspect without pretending it is an executable provider configuration.

## Retry ownership

Decision defaults to one attempt. Enable bounded retries explicitly:

```php
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;

$pending = Decision::using('typesafe')
    ->with(
        input: $state,
        questions: $questions,
        retryPolicy: new DecisionRetryPolicy(
            maxAttempts: 3,
            baseDelayMs: 250,
            maxDelayMs: 4000,
        ),
    )
    ->create();
```

The Decision execution session is the single retry owner. It can retry transient transport failures and configured `408`, `429`, `5xx`, and `529` responses, honors bounded `Retry-After`, and memoizes both success and terminal failure. Do not add a second HTTP retry middleware for the same operation.

## Events and telemetry

`DecisionRuntime` exposes `onEvent()` and `wiretap()` on its event root. The lifecycle events are:

- `DecisionStarted` and `DecisionCompleted` or `DecisionFailed`
- `DecisionAttemptStarted` and `DecisionAttemptSucceeded` or `DecisionAttemptFailed`

Polyglot projects them as `sdm.decision` and child `sdm.decision.attempt` spans. Default event and telemetry payloads contain IDs, model/driver, primitive count, timing, retry decisions, status, and token counts. They omit state, instructions, criteria, provider bodies, credentials, and exception messages.

## Live smoke test

Ordinary tests never call TypeSafe. To run the bounded mixed-primitive smoke explicitly:

```bash
POLYGLOT_TYPESAFE_LIVE=1 php vendor/bin/pest packages/polyglot/tests/Integration/TypesafeLiveTest.php
```

The command resolves `TYPESAFE_API_KEY` through the existing environment loader. It fails when explicitly enabled without a key and prints only safe endpoint/model/count/usage evidence.

## Deliberate limits

Decision has no stream API: an SDM response is one typed result. Generating question definitions through Instructor, Dynamic, or Agents is intentionally deferred until the low-level contract has settled. Those layers can later produce `Questions` or portable request payloads without moving Decision ownership out of Polyglot.
