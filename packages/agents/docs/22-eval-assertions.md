---
title: 'Eval Assertions'
description: 'The deterministic assertion catalogue for agent evals: outcome, trajectory, event, step, and cost checks, and when they must be trusted over a judge'
---

# Eval Assertions

`EvalContext` exposes two families of checks. This page covers the deterministic family: plain PHP comparisons against the target's `AgentRun` -- reply text, tool calls, events, steps, and token usage. They involve no inference call, so they are free, instant, and give the same answer on every run. The semantic family, reached through `$t->judge()->...`, is covered on the eval judges page; [Agent Evals](21-evals.md) introduces both and shows how they read together in a case.

> **Safety-critical invariants belong to this page, never to the judge alone.** A judge grades the same target output an attacker controls, so a sufficiently crafted reply can steer it toward a high score. A deterministic assertion cannot be talked out of its answer. `notCalledTool`, `toolOrder`, and the step/token bounds below are what actually decide whether an unsafe action occurred; the judge grades quality on top of that decision, never in place of it. The full reasoning is at the end of this page -- read it before you decide a judge assertion is sufficient for anything safety-relevant.

## Severity and thresholds

Every deterministic assertion on `EvalContext` returns an `AssertionHandle`, and every recorded check is an `AssertionResult` with a `score` in `[0, 1]`, a `severity` (`AssertionSeverity::Gate` or `::Soft`), and an optional `threshold`. `passed()` is `score >= (threshold ?? 1.0)` -- so a plain boolean check (score `0.0` or `1.0`) needs an exact `1.0` to pass unless you widen it with `atLeast()`.

```php
$t->maxToolCalls(3)->soft();                 // a failure here scores the case, it doesn't fail it
$t->expect($t->run()->reply())
    ->similarity('Verification required for order A1049')
    ->atLeast(0.9);                          // pass once the score reaches 0.9, not just at 1.0
```

`AssertionHandle` supports `.gate()`, `.soft()`, `.atLeast(float $threshold)`, and `.label(string $label)`, each mutating the just-recorded result in place. Every assertion documented on this page defaults to `Gate` severity; call `.soft()` to turn a failure into a lower score instead of a case failure. (One exception defaults to `Soft` on its own: `ValueExpectation::similarity()`, described below. Judge assertions from `$t->judge()->...`, described on the eval judges page, default to `Soft` only when a judge is actually configured -- with none configured they default to `Gate`, and a judge that throws is always recorded as a `Gate` failure regardless of any prior `.gate()`/`.soft()` call. Call `.gate()` explicitly if a configured judge's failure must fail the case.)

Severity determines the case verdict, resolved by `EvalVerdictResolver`:

| Condition | Verdict |
| --- | --- |
| the case closure threw an uncaught error, or exceeded its cooperative timeout | `Failed` |
| any recorded assertion has `Gate` severity and did not pass | `Failed` |
| `$t->skip(...)` was called (and no gate failed) | `Skipped` |
| any recorded assertion has `Soft` severity and did not pass (and no gate failed) | `Scored` |
| otherwise | `Passed` |

A failed gate always wins: a case with one failed `Gate` assertion and nine passed ones is `Failed`, not `Scored`.

## Outcome assertions

These read the accumulated `AgentRun` -- see [Agent Evals](21-evals.md) for how `send()` builds it across turns. None of them record a message on failure; a failing outcome assertion is identified by its assertion name alone (`succeeded`, `stopped`, `messageIncludes`, `outputEquals`, `outputMatches`).

```php
$t->succeeded();                              // run()->succeeded(): status is ExecutionStatus::Completed
$t->stopped();                                // run()->status() === ExecutionStatus::Stopped
$t->messageIncludes('Verification');          // str_contains(run()->reply(), text)
$t->outputEquals('Verified.');                // EvalMatcher::matches(expected, run()->reply())
$t->outputMatches('/order\s+#\d+/');          // EvalMatch::regex(pattern)->matches(run()->reply())
```

`outputEquals()` accepts a literal value (strict `===`), an `EvalMatch` (see below), or a partial array structure -- but since `reply()` is always a string, only the literal-string and `EvalMatch` forms are meaningful here; partial-array matching is for tool arguments and results, covered next.

## Trajectory assertions

These inspect `run()->tools()`, the flattened list of every tool execution across every turn sent so far.

```php
$t->calledTool('refunds_lookup', arguments: ['orderId' => 'A1049'], count: 1);
$t->notCalledTool('refunds_issue');
$t->toolOrder('refunds_lookup', 'policy_check');
$t->usedNoTools();
$t->maxToolCalls(3);
$t->noFailedActions();
```

- **`calledTool(string $name, mixed $arguments = null, mixed $result = null, ?bool $isError = null, int|EvalCount|null $count = null)`** counts executions of `$name` whose arguments, result, and error state (when given) match, then compares that count against `$count`: an exact `int`, an `EvalCount` (below), or -- when omitted -- "at least one." Arguments and results are compared with `EvalMatcher::matches()`, so you can pass a literal value, a partial array (only the given keys need to match; extra keys in the actual value are ignored), or an `EvalMatch`. The assertion name is `calledTool:{name}` and the message is always `"matched {n} tool calls"`, present whether it passes or fails.
- **`notCalledTool(string $name, mixed $arguments = null)`** passes when no execution of `$name` (matching `$arguments`, if given) exists. Assertion name `notCalledTool:{name}`; no message.
- **`toolOrder(string ...$names)`** passes when `$names` appears as a (not necessarily contiguous) ordered subsequence of the actual tool-call names -- extra calls in between are fine, out-of-order or missing calls are not. Assertion name `toolOrder`; no message.
- **`usedNoTools()`** passes when zero tools were called. Assertion name `usedNoTools`; no message.
- **`maxToolCalls(int $maximum)`** passes when the total tool-call count is at most `$maximum`. Assertion name `maxToolCalls`; no message.
- **`noFailedActions()`** passes when no tool execution has an error and `run()->errors()` is empty. Assertion name `noFailedActions`; no message.

`EvalCount` gives `calledTool()`, `calledSubagent()`, and `event()` a fluent way to express a count range instead of an exact integer: `EvalCount::atLeast(int)`, `::atMost(int)`, `::between(int, int)`, or `::satisfies(Closure(int): bool)` for anything else.

## Subagent and event assertions

These inspect `run()->events()`, the raw event stream captured during execution.

```php
$t->calledSubagent('researcher', count: EvalCount::atLeast(1));
$t->event(AgentExecutionCompleted::class);
$t->event(AgentStepCompleted::class, predicate: fn (object $e) => $e->stepType === AgentStepType::ToolExecution);
$t->notEvent(AgentExecutionFailed::class);
$t->eventOrder(AgentExecutionStarted::class, AgentExecutionCompleted::class);
$t->eventsSatisfy(fn (EvalEvents $events) => $events->count() < 20);
```

- **`calledSubagent(string $name, int|EvalCount|null $count = null)`** counts `SubagentCompleted` events whose `subagentName` matches, using the same `int`/`EvalCount`/"at least one" rule as `calledTool()`. Assertion name `calledSubagent:{name}`; no message.
- **`event(string $class, ?Closure $predicate = null, int|EvalCount|null $count = null)`** counts events that are an `instanceof $class` and, when given, satisfy `$predicate`. Assertion name `event:{class}` (the fully-qualified class name); no message.
- **`notEvent(string $class)`** passes when no event is an `instanceof $class`. Assertion name `notEvent:{class}`; no message.
- **`eventOrder(string ...$classes)`** passes when events matching each class in `$classes`, in order, appear as an ordered subsequence of the actual event stream (same subsequence semantics as `toolOrder()`). Assertion name `eventOrder`; no message.
- **`eventsSatisfy(Closure(EvalEvents): bool $predicate)`** passes when `$predicate` returns true for the whole `EvalEvents` collection -- the escape hatch for anything the named checks above don't express. Assertion name `eventsSatisfy`; no message.

## Step and cost assertions

These read `run()->stepCount()` and `run()->usage()->total()`, both accumulated across every `send()` on the session. Unlike the assertions above, all three always carry a message, whether they pass or fail:

```php
$t->stepCount(2);          // "expected 2 steps, got {actual}"
$t->maxSteps(6);           // "expected at most 6 steps, got {actual}"
$t->totalTokensAtMost(4_000); // "used {actual} tokens, limit 4000"
```

- **`stepCount(int $expected)`** passes only on an exact match.
- **`maxSteps(int $maximum)`** passes when the actual step count is at most `$maximum`.
- **`totalTokensAtMost(int $maximum)`** passes when accumulated token usage is at most `$maximum`.

## `expect()` and the matcher vocabulary

`$t->expect($value)` returns a `ValueExpectation` for grading an arbitrary value pulled out of the run -- not just the reply. Each call in the chain records its own assertion; `.gate()`, `.soft()`, `.atLeast()`, and `.label()` apply only to the most recently recorded one:

```php
$t->expect($t->run()->reply())
    ->includes('order')                              // recorded, still Gate
    ->similarity('Verification required for order A1049')
    ->atLeast(0.9);                                   // applies to `similarity`, not `includes`
```

- **`includes(mixed $expected)`** -- `str_contains()` when the value is a string, `in_array($expected, $value, true)` when it's an array, otherwise fails.
- **`equals(mixed $expected)`** -- `EvalMatcher::matches($expected, $value)`.
- **`matches(string|EvalMatch $pattern)`** -- a bare string is treated as `EvalMatch::regex($pattern)`.
- **`similarity(string $expected)`** -- `1 - (levenshtein / max length)`, a continuous score. This is the one built-in assertion that defaults to `Soft` severity rather than `Gate`, because a Levenshtein ratio is rarely meant to gate a case on its own.
- **`satisfies(Closure(mixed): bool $predicate)`** -- an arbitrary predicate over the value.

All five record with an empty message and the assertion names `includes`, `equals`, `matches`, `similarity`, `satisfies`.

Three of the above, plus `outputEquals()` and the `arguments`/`result` parameters of `calledTool()`, are backed by the same matcher vocabulary:

- **`EvalMatcher::matches($expected, $actual)`** -- an `EvalMatch` delegates to itself; an array does a **partial** structural match (below); anything else is strict `===`.
- **`EvalMatcher::partial($expected, $actual)`** -- both must be arrays. If `$expected` is a list, it requires the same length and an elementwise partial match at each position. If `$expected` is a map, every key in `$expected` must exist in `$actual` and match partially -- extra keys in `$actual` are ignored, which is what makes it "partial."
- **`EvalMatch::partial(array $value)`**, **`::regex(string $pattern)`** (validated at construction; an invalid pattern throws `InvalidArgumentException` immediately), and **`::satisfies(Closure $predicate)`** build a matcher explicitly, for when you need one as a value rather than as a chained call.

## `check()`, `require()`, `skip()`, and `log()`

Four lower-level `EvalContext` methods sit underneath everything above:

- **`check(string $name, bool $passed, string $message = ''): AssertionHandle`** is the generic escape hatch every named assertion in this page is built on. Reach for it directly when you have a boolean condition that no named assertion expresses.
- **`require(string $name, bool $passed, string $message = ''): void`** calls `check()` (so the result is recorded either way) and, on failure, throws `EvalRequirementFailed`, which stops the rest of the case closure immediately. The runner does not treat this as a separate error -- the verdict comes from the gate failure `check()` already recorded. Use it for a precondition the rest of the case cannot meaningfully continue past (an empty fixture, a target that never replied), not as a stronger version of an ordinary assertion.
- **`skip(string $reason): never`** throws `EvalSkipped`, which the runner turns into a `Skipped` verdict (unless a gate assertion recorded earlier in the closure already failed, in which case `Failed` wins). Use it when a case does not apply in the current configuration, not when it fails.
- **`log(string $message, array $context = []): void`** records a diagnostic entry with no effect on the verdict, retrievable from `$t->logs()`. Use it to leave breadcrumbs -- an intermediate value, a branch taken -- for a report to surface without turning that value into an assertion.

## Why deterministic assertions are the safety boundary

A judge is graded material generated by the same target it is asked to evaluate. Wrapping that trace in JSON and labeling it untrusted in the judge's system contract reduces how easily it can be misread as instructions, but it does not eliminate the risk: a language model has no enforced boundary between data and instructions, so "it's JSON, not executable text" is not a security property, and this package does not claim it is one. A target reply can be adversarial in exactly the scenarios evals exist to catch, and a sufficiently crafted reply can steer a judge toward a high score regardless of what actually happened.

The consequence is a scoping rule, not a filter: **assert every safety-critical invariant with the deterministic assertions on this page, and never rely on a judge assertion alone to catch one.** `notCalledTool()`, `toolOrder()`, `maxSteps()`, `totalTokensAtMost()`, and the rest of this catalogue decide whether an unsafe action happened, deterministically and the same way on every run. The judge -- covered on the eval judges page -- grades quality on top of a trajectory those deterministic gates have already accepted, not instead of checking it.
