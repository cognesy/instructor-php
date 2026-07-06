# InstructorPHP xqa Stage 1 Plan

Date: 2026-04-09

## Goal

Integrate the **standard QA mechanisms already used by InstructorPHP that
`xqa` supports out of the box** into a first repo-local `xqa` rollout.

This stage is intentionally limited to mechanisms that can be wrapped through
configuration and existing `xqa` capabilities. It is not the plugin or
extension-architecture stage.

## Context

InstructorPHP already has a sophisticated quality system documented in
[QUALITY.md](/Users/ddebowczyk/projects/instructor-php/QUALITY.md).

That system includes both:

1. standard mechanisms that `xqa` can already orchestrate or normalize
2. custom repo-specific mechanisms that go beyond current `xqa`
   configuration-only support

This Stage 1 plan covers only the first category.

## Standard mechanisms already within xqa reach

The following existing InstructorPHP mechanisms are within or near the current
`xqa` capability set:

- `composer test`
  - Pest-based default local test run
- `composer test-all`
  - still Pest-based, broader suite
- `composer qa:phpstan`
  - PHPStan
- `composer qa:psalm`
  - Psalm
- `composer qa:semgrep`
  - Semgrep
- `composer dead-code`
  - PHPStan-based dead-code detection
- `composer unused-deps`
  - composer-unused

These are the mechanisms Stage 1 may wrap.

## Standard mechanisms not in scope for Stage 1

Even if they are common tools or common workflows, these are not part of the
first Stage 1 rollout because `xqa` does not yet support them cleanly enough
or because they would blur the rollout:

- `composer qa:pint`
  - Pint is not currently a first-class `xqa` tool
- `composer bench`
  - PHPBench is not currently a first-class `xqa` tool
- `act pull_request ...`
  - local CI matrix reproduction is not currently a first-class `xqa`
    surface

## Custom InstructorPHP mechanisms excluded from Stage 1

These belong to Stage 2, not Stage 1:

- `composer qa:docs`
- `composer docs drift`
- `composer test:telemetry-interop`
- package-local docs QA rules and doctools profiles
- env-sensitive live backend validation
- detailed plugin/extension architecture for user-provided QA code

## Working strategy

Stage 1 should start small and prove operational value quickly.

Recommended rollout order:

1. establish the deterministic core lane:
   - `composer test`
2. wrap the standard static-analysis lane(s):
   - `composer qa:phpstan`
   - `composer qa:psalm`
   - `composer qa:semgrep`
3. optionally evaluate:
   - `composer dead-code`
   - `composer unused-deps`
4. document the resulting workflow and dogfood `xqa progress`

## First profile recommendation

First `xqa` rollout should likely use:

- `default`
  - `composer test`
- `analysis`
  - PHPStan + Psalm + Semgrep as separate tools or separate profiles

Potential first profile set:

- `default`
- `phpstan`
- `psalm`
- `architecture` or `semgrep`

Maybe later in Stage 1:

- `dead-code`
- `deps`

## Scope freeze

Stage 1 is now frozen to the following mechanisms.

### Adopt now in Stage 1

- `composer test`
  - deterministic default local behavioral lane
- `composer qa:phpstan`
  - standard supported PHPStan lane
- `composer qa:psalm`
  - standard supported Psalm lane
- `composer qa:semgrep`
  - standard supported Semgrep lane

These are the first mechanisms `xqa` should wrap in InstructorPHP.

### Evaluate later within Stage 1

- `composer dead-code`
  - likely supportable because it is still PHPStan-based, but should be proven
    separately instead of mixed into the first config slice
- `composer unused-deps`
  - likely near current `composer-unused` support, but should be evaluated only
    after the first standard lanes are working

### Explicitly defer from Stage 1

- `composer qa:pint`
  - `xqa` does not yet have first-class Pint support
- `composer bench`
  - `xqa` does not yet have first-class PHPBench support
- `act pull_request -W .github/workflows/php.yml -j build`
  - CI/matrix reproduction is not a current `xqa` product surface
- `composer test-all`
  - broader suite, but not the first deterministic default lane
- `composer test:telemetry-interop`
  - env-sensitive live backend suite; belongs outside Stage 1
- `composer qa:docs`
- `composer docs drift`
  - these are custom InstructorPHP docs QA/drift surfaces and belong to Stage 2

### Stage 1 / Stage 2 boundary

Stage 1 covers only standard supported mechanisms that can be wrapped through
current `xqa` configuration and normalizer support.

Stage 2 is explicitly responsible for:

- custom docs QA
- docs drift
- env-sensitive live integration suites
- CI matrix reproduction
- any extension/plugin architecture required for user-provided code

## Why this shape

Because InstructorPHP already has a mature QA policy and the value of `xqa`
here is not to invent more QA, but to add a shared operational layer:

- `xqa doctor`
- `xqa profile run ...`
- `xqa snap store`
- `xqa progress`
- grouped reports and remediation planning

## Success criteria

Stage 1 is successful if:

1. the repo has a valid `.xqa/config.yaml`
2. `xqa doctor` succeeds in the repo
3. the chosen Stage 1 standard profiles run successfully
4. `xqa progress` can be used against at least one standard supported lane
5. the rollout is clearly documented without pretending to cover the custom QA
   system yet

## Risks

- overreaching into custom docs QA or env-sensitive suites too early
- trying to wrap all standard mechanisms at once instead of proving the base
  rollout first
- flattening InstructorPHP's existing nuanced quality policy into a simplistic
  `default` story

## Task breakdown

1. Assess and freeze the exact Stage 1 standard mechanism scope.
2. Implement repo-local `xqa` setup for the chosen standard mechanisms.
3. Evaluate supported-but-secondary standard mechanisms (`dead-code`,
   `unused-deps`) and decide add-now vs defer.
4. Document the Stage 1 rollout and its explicit boundary with Stage 2.
5. Dogfood the Stage 1 rollout and prove measurement-first value.
6. Capture Stage 1 lessons and hand off custom-mechanism analysis to Stage 2.

## Review status

This plan is being created in direct response to an explicit user request to
split the work into Stage 1 and Stage 2 and to plan Stage 1 through `bd`
tasks. That is treated as approval to proceed with Stage 1 task creation unless
new constraints are raised.
