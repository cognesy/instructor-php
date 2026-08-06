---
title: 'Running Evals'
description: 'The agents-eval CLI, reading compact and verbose output, verdicts and exit codes for CI, why a single run is not a measurement, and wiring evals into PHPUnit and Pest'
---

# Running Evals

Writing an eval case (see [Evals](21-evals.md)) is only half the job -- this page covers actually running the suite: the `agents-eval` CLI, how to read what it prints, how a case's assertions turn into a verdict and an exit code, why a single run of a stochastic target graded by a stochastic judge is not a trustworthy measurement, and how to wire eval runs into PHPUnit and Pest so CI fails the way a normal test failure does.

## The `agents-eval` CLI

`EvalApplication::run(array $argv, ...)` is the whole CLI; `bin/agents-eval` is a thin executable wrapper around it, registered as a Composer `bin`, so an installed package exposes it as `vendor/bin/agents-eval`. It takes an optional path (default `evals`) and a set of flags:

```text
Usage: agents-eval [path] [options]

Options:
  --filter=<glob>        Include IDs matching a glob
  --tag=<tag>            Require a tag; repeatable
  --exclude-tag=<tag>    Exclude a tag; repeatable
  --strict               Fail the run for scored cases
  --timeout=<seconds>    Set a cooperative per-trial budget
  --repeat=<n>           Run every case n times, each with a fresh session
  --pass-rate=<r>        Fraction of trials that must pass, 0 < r <= 1
  --junit=<path>         Write JUnit XML
  --list                 List discovered IDs only
  --verbose              Show passing checks and logs
  --json                 Emit run result JSON
  --skip-report          Disable configured reporters
  -h, --help             Show this help
```

That block is the tool's actual `--help` output, not a paraphrase.

`EvalDiscovery::in($root)->discover()` walks `$root` recursively for every `*.eval.php` file, requires it, and normalizes what it returns (`AgentEval`, `AgentEvalSet`, or an array of `AgentEval`) into one `AgentEvals` suite; a file's discovered id comes from its path relative to `$root` (`.eval.php` stripped), and discovery overwrites any id the eval already carries from `withId()` in its own file. Reaching `$root/evals.config.php` and requiring it is how the runner gets its `EvalConfig` (target, judge, reporters) -- if that file doesn't exist, `EvalConfig::default()` runs with no target configured, which fails fast rather than running nothing.

- `--filter=<glob>` matches against the discovered id with `fnmatch()` -- one glob, not a list.
- `--tag=<tag>` is repeatable and **AND**-combined: an eval must carry every required tag to be selected.
- `--exclude-tag=<tag>` is repeatable and **OR**-combined: an eval carrying any excluded tag is dropped, even if it also matches every required tag.
- `--strict` changes what counts as a CI failure -- see [Verdicts and exit codes](#verdicts-and-exit-codes) below.
- `--timeout=<seconds>` is a **cooperative** per-trial budget: the runner checks elapsed wall time after a trial's test closure returns and records a timeout error if it ran long, rather than interrupting execution while it's in flight.
- `--junit=<path>` adds a `JUnitEvalReporter` for this run only, on top of whatever `evals.config.php` already configured.
- `--list` prints discovered (and filtered) ids, one per line, without running anything.
- `--json` prints the run result's `toArray()` as pretty JSON after the run completes, alongside whatever reporters also ran.
- `--skip-report` runs the suite but calls no configured reporter at all -- useful with `--json` when you want the JSON and nothing else on stdout.

An unrecognized `--flag` and an out-of-range `--repeat`/`--pass-rate`/`--timeout` are both refused outright as usage errors (`EvalExitCode::ConfigurationError`, exit code 2) rather than silently coerced -- `--repeat=2.5` is rejected, not truncated to 2, because running a different number of trials than requested would corrupt the very measurement `--repeat` exists to produce.

## Reading output: compact vs verbose

`ConsoleEvalReporter` (see [Eval traces and artifacts](24-eval-traces-and-artifacts.md#console-output) for the base format) stays compact by default: one line per eval case, plus a `passed=/failed=/scored=/skipped=` summary and a `TOKENS target=.../judge=.../total=...` footer at the end of the run. `--verbose` (or `ConsoleEvalReporter::fromWriter($write, verbose: true)`) adds a `TARGET ...` line per case, a `JUDGE ...` line and `EVIDENCE ...` lines per judged assertion, and `LOG ...` lines for anything the case logged with `$t->log(...)`.

When a case fails, the console line and the PHPUnit/Pest failure text (below) are usually enough to see *why*; when they aren't -- when you need the actual tool arguments, the full judge trace, or the raw events -- that detail lives in the artifact files described on the [traces and artifacts](24-eval-traces-and-artifacts.md#the-artifact-layout) page, written by `ArtifactEvalReporter` under `.instructor/evals` (or wherever you configured it).

## Verdicts and exit codes

Each case resolves to one of four `EvalVerdict` cases:

```php
enum EvalVerdict: string {
    case Passed = 'passed';
    case Failed = 'failed';
    case Scored = 'scored';
    case Skipped = 'skipped';
}
```

`EvalVerdictResolver::resolve()` derives it from the case's assertions, in this order: an uncaught error or a failed gate assertion is always `Failed`; otherwise a skip (`$t->skip(...)`) is `Skipped`; otherwise a failed *soft* assertion (with nothing gated failing) is `Scored`, not `Failed` -- a soft miss is a signal to look at, not a hard break; anything else is `Passed`. This is the single-run rule; a repeated case (`--repeat`) resolves its verdict differently and a missed pass rate there IS a hard `Failed` even when every shortfall was soft -- see [Repetition and pass rate](#repetition-and-pass-rate) below.

`EvalRunResult::exitCode(?bool $strict = null)` (defaulting to the run's own `--strict` setting) turns those verdicts into one `EvalExitCode`:

```php
enum EvalExitCode: int {
    case Success = 0;
    case EvalFailure = 1;
    case ConfigurationError = 2;
}
```

- **`Success` (0)** -- every case passed (or was soft-`Scored` in advisory mode), and no reporter raised an error.
- **`EvalFailure` (1)** -- at least one case is `Failed`, or (with `--strict`) at least one case is `Scored`, or a reporter itself threw (an artifact write failure, for example) even if every eval verdict was fine.
- **`ConfigurationError` (2)** -- the CLI itself couldn't run: a bad flag, a missing eval directory, `evals.config.php` not returning an `EvalConfig`, or any other `Throwable` caught by `EvalApplication::run()` before a suite ran at all.

`--strict` is the knob that decides whether a `Scored` case is CI-blocking. Without it, a soft assertion below threshold is visible in the console and in artifacts but doesn't fail the run -- useful while iterating on a judge's grading rubric. With it, `Scored` behaves like `Failed` for the exit code (and for `JUnitEvalReporter`'s `<failure>` output, and for whether `EvalTestFailureMessage` includes that case), which is what you want once the suite is a CI gate rather than a dashboard.

## Repetition and pass rate

A single run of `agents-eval` grades a stochastic target with (usually) a stochastic judge, and takes exactly one sample. A threshold applied to one draw from a distribution says nothing about that distribution's spread: a case that happens to pass on that one draw and a case that reliably passes 95% of the time are indistinguishable from a single run, and a case that flakes will pass or fail CI depending on nothing but which draw it happened to get that day. `--repeat=N` and `--pass-rate=R` turn "did this pass once" into "how often does this pass," which is the only form that supports tracking a regression over time instead of chasing single-run noise.

### Running a case more than once

```text
--repeat=<n>           Run every case n times, each with a fresh session
--pass-rate=<r>        Fraction of trials that must pass, 0 < r <= 1
```

`--repeat=5 --pass-rate=0.8` runs every selected case five times. Each trial opens a **fresh session** through the target -- nothing about the conversation, the assertion collector, or the log collector carries over from one trial to the next, so trial 3 grades the same starting conditions trial 1 did, not an agent that has already answered twice. The cooperative `--timeout` stays a *per-trial* budget, not a budget for the whole repeated case -- a `--repeat=5` run therefore permits up to five times the total wall clock a single trial's `--timeout` allows, not the same ceiling stretched across all five.

A repeated case's verdict is a **k-of-N** decision: `EvalVerdictResolver::requiredPasses(N, passRate)` computes `ceil(passRate * N)` (with a small floating-point tolerance subtracted before rounding, so a product that lands a hair above its intended integer in binary floating point -- `0.07 * 100` is `7.0000000000000009` -- doesn't round up into demanding 8 passes out of 100 where 7 were asked for) and clamps the result to at least `1` and at most `N` -- a pass rate above `0` can never be satisfied by zero passing trials, and a repeated case never needs more passes than it has trials. The case passes when its pass count meets or exceeds that number. Only a trial whose own verdict is `Passed` counts toward the rate -- a trial that came back `Scored` (a soft miss) counts as a miss for the rate, which makes `--pass-rate` an explicit gate: `--repeat=5 --pass-rate=0.8` really does fail a case that misses its rate, even in an otherwise-advisory (non-`--strict`) run, and even when every individual shortfall was a soft assertion. This is the one place in the harness where a soft assertion produces a hard `Failed` rather than a `Scored`. Without that rule, a repeated run could never fail anything unless `--strict` was also set, and the flag would measure nothing. A case whose every trial skipped resolves to `Skipped` as a whole; a skip alongside trials that ran counts as a miss like any other non-pass, not as a partial exemption.

Verified directly against the CLI and the harness:

```text
$ agents-eval evals --repeat=5 --pass-rate=0.8
PASS 4/5  refund-requires-verification  judge=0.80+/-0.20  (12.4ms)
passed=1 failed=0 scored=0 skipped=0
TOKENS target=710 judge=0 total=710
```

The rate line replaces the ordinary `[VERDICT] id (duration)` line whenever a case ran more than once: `PASS 4/5` (verdict-derived label and `passed/total`), the case id, an optional `judge=<mean>+/-<stddev>` when at least one assertion in the case was judged (omitted entirely, not printed as `judge=0.00`, when nothing was judged), and the total duration across every trial. `--verbose` additionally lists each trial:

```text
  TRIAL 1/5 passed judge=0.90
  TRIAL 2/5 passed judge=0.90
  TRIAL 3/5 passed judge=0.90
  TRIAL 4/5 passed judge=0.90
  TRIAL 5/5 scored judge=0.40
  TARGET steps=1 tools=0 tokens=142 stop=none
```

`repeat=1` is not a special case bolted on top of repetition -- `EvalRunner::runCase()` returns the single trial's `EvalResult` unchanged when `$options->repeat() === 1`, so a case that runs once produces exactly the object, console line, and serialized JSON it always did. There is no `repetition` key in a single-trial result's `toArray()` at all, and console output for the default options (`EvalRunOptions::default()`, i.e. `repeat=1`) is byte-for-byte identical to explicitly passing `--repeat=1`.

### The mean and standard deviation reported

For a repeated case, `EvalResult::judgeScoreMean()` and `EvalResult::judgeScoreStdDev()` (and the underlying `EvalRepetition` that computes them) summarize every judged assertion's score across every trial -- one score per judged assertion per trial, so a case with a single judged assertion is simply the mean/deviation of that assertion's score over the trials. Both are `null` when nothing in any trial was judged, reported as absence rather than a fabricated `0.0`; the standard deviation is `0.0` (never a division by zero) when there's exactly one judged score to summarize.

The deviation reported is the **population** standard deviation -- the sum of squared deviations divided by `N`, not `N-1`. The trials are treated as the population being described (the observed spread of the sample that produced this verdict), not as a sample of some larger population, and the two conventions diverge by roughly 12% at `N=5`, so the choice is made explicit here rather than left for a reader to assume either way.

### Why judge temperature matters for `--repeat`

Repetition is only a clean measurement of **target** variance if the judge itself is not also a second source of noise. Slice 6 (the repetition feature itself) does not introduce any temperature default of its own -- there is no package-wide "judges default to temperature 0" rule. The only place a default exists is `UseJudgeInference` (covered on the [eval judges](23-eval-judges.md) page): it sets `temperature: 0.0` for a judge driver it builds, but that default is a plain array entry a caller's own `$options` overwrite (`new UseJudgeInference(options: ['temperature' => 0.7])`), not an enforced floor. `AgentLoopJudge` never installs `UseJudgeInference` -- or any temperature -- on your behalf. If your judge builder leaves inference at the provider's default (or explicitly overrides `UseJudgeInference`'s own default), some of the spread `--repeat` reports back is judge noise mixed in with target noise, and you can't separate the two from the reported numbers alone. Pin the judge's temperature explicitly and leave it unoverridden if you want `--repeat`'s variance to describe the target and nothing else.

Note that this doesn't show up in provenance either way: `judge.temperature` in the `provenance` block (see [traces and artifacts](24-eval-traces-and-artifacts.md#provenance-why-a-score-needs-context-to-mean-anything)) is always `null`, regardless of `--repeat` or of what `UseJudgeInference` was configured with -- there is no way to read a built judge loop's inference options back out, so the harness cannot confirm from the artifact alone that a repeated run's judge was actually temperature-pinned. That has to be a fact you know about your own judge configuration.

### Per-trial detail in artifacts and JSON

`ArtifactEvalReporter` writes one `trials/NNN/details.json` (numbered 1-up in execution order) per trial of a repeated case, alongside that trial's own `target-steps.jsonl` and `judges/` files -- nothing here is written for a case that ran once, since its single trial already **is** the case's own artifact directory. The case's own `details.json` additionally gains a `repetition` key:

```json
{
  "repetition": {
    "trials": 5,
    "passed": 4,
    "required": 4,
    "satisfied": true,
    "judgeScoreMean": 0.8,
    "judgeScoreStdDev": 0.2,
    "results": [
      { "trial": 1, "verdict": "passed", "duration": 0.004, "error": null, "skipReason": null, "assertions": ["..."], "tokens": {"target": 142, "judge": 0, "total": 142} }
    ]
  }
}
```

Every trial's own assertions survive here, not just the aggregate's -- the aggregate's top-level `assertions` (and `run`, `logs`, `error`) come from a single **representative** trial (the first trial that didn't pass, or the first trial when all of them did), so a reporter always has one concrete failure to point at, while the full per-trial record stays in `repetition.results` for anyone who needs to see all five -- including which trial failed and why. `EvalResult::tokens()` for the case as a whole sums every trial's usage rather than reporting one trial's cost, since N trials really did spend N trials' worth of target and judge tokens; that aggregate is not a per-trial figure, but each trial's own `tokens` survives in `repetition.results[].tokens` (as in the example above), so a per-trial mean is still recoverable from there if you need one.

`--json` on the CLI prints `EvalRunResult::toArray()` with no extra envelope, so each result's `repetition` object is present exactly as above -- but the run-level `provenance.repeat` convenience field described on the traces-and-artifacts page is populated only by `ArtifactEvalReporter` (which fills in `repeat`, `package`, `startedAt` from its own environment when it writes `summary.json`/`details.json`), not by every code path that calls `toArray()`. Read the actual trial count from `repetition.trials` on a result if you're consuming `--json` output directly without an artifact reporter attached.

## Test-suite integration

`PHPUnitEvalReporter::default()` and `PestEvalReporter::default()` both implement `CanFailAgentEvalTestSuite`, a marker interface for reporters allowed to propagate a run's own final verdict into the host test runner:

```php
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\PHPUnitEvalReporter; // or PestEvalReporter

$config = EvalConfig::default()
    ->withTarget(/* ... */)
    ->withReporter(PHPUnitEvalReporter::default());
```

Both reporters do nothing on `onRunStarted`/`onEvalCompleted` -- all the work happens in `onRunCompleted`, where `PHPUnitEvalReporter` calls `Assert::assertSame(EvalExitCode::Success, $result->exitCode(), ...)` and `PestEvalReporter` calls Pest's `expect($result->exitCode())->toBe(EvalExitCode::Success, ...)`. Either way, a suite that would exit non-zero on the CLI fails the enclosing PHPUnit or Pest test the same way any other assertion failure would -- these reporters translate a completed run's result into a framework failure; they never re-execute a case or judge anything a second time. `EvalRunner` also runs any `CanFailAgentEvalTestSuite` reporter's `onRunCompleted` last, after every other configured reporter has already run and had a chance to record its own error, so a PHPUnit/Pest failure also reflects a reporter error (a failed artifact write, for example) that happened alongside the eval results themselves.

The failure message both reporters raise comes from `EvalTestFailureMessage::fromResult($result)`, which is built to be diagnosable from CI log output alone, without opening an artifact directory: a summary line, then per failing case its id and description, its repetition summary when it ran more than once, the target's step count and stop reason, and every failed judged assertion's evidence. This is a real, verified example -- not a paraphrase -- for a case run with `--repeat=5 --pass-rate=0.8` under `--strict` that passed 3 of its 5 trials:

```text
Agent eval suite failed (1 failed, 0 scored, 0 reporter errors; strict mode).
- [failed] refund-requires-verification — Refund replies require verification.
  - repetition: passed 3/5, needed 4/5 (judge mean 0.70 +/- 0.24)
  - target: steps=1 stop=none
  - judge:closed_qa: Does the reply require verification? [soft]: score 0.40, required 0.80 — trial 4 scored 0.40
```

For a case that ran once, the same message omits the `repetition:` line entirely rather than printing it for a case with nothing to repeat.

## CI guidance

Run `agents-eval` (or a PHPUnit/Pest wrapper around it) as its own CI step, not folded silently into an existing unit-test job -- it has a different failure mode (a `Scored` case is informative, not necessarily a regression, unless `--strict` is set) and a different cost profile from ordinary tests. A few things worth deciding deliberately rather than defaulting into:

- **`--strict` in CI, advisory locally.** Gate merges on `--strict` (a soft miss blocks the merge) once a judge's rubric is trusted; leave it off while a case or a judge prompt is still being tuned, so `Scored` results are visible without blocking anything.
- **`--repeat` costs multiply, not add.** Every trial is a full run of the target, and every judged assertion in every trial is a full run of the judge if it's agentic -- `--repeat=5` on a suite with agentic judges is roughly five times the token spend of a single pass, split `target`/`judge` in the console footer and in `summary.json`'s `tokens` block (see [traces and artifacts](24-eval-traces-and-artifacts.md#cost-target-and-judge-never-folded-together)). Reserve `--repeat` for the cases where flakiness actually matters -- a nightly or pre-release job, or a small set of historically noisy cases -- rather than applying it to every case on every commit.
- **Provenance is what makes two CI runs comparable.** A score from last week's CI run and today's are only comparable if the `provenance` block (target/judge model, package version, git sha) recorded alongside each one actually matches; without it, a regression in the target is indistinguishable from a judge model change, a prompt revision, or a package upgrade that happened between the two runs. Keep `ArtifactEvalReporter` (or at minimum `JUnitEvalReporter`, which records less but is often what CI already consumes) wired into the CI config, not just the console reporter, so that context survives past the build log.

## Where to go next

- **[Evals](21-evals.md)** -- the case API, targets, and datasets that `agents-eval` discovers and runs.
- **[Eval traces and artifacts](24-eval-traces-and-artifacts.md)** -- what a case's console line, `judges/NNN.json`, and `provenance` block mean in full, including the `guardsWarningObserved` caveat referenced above.
