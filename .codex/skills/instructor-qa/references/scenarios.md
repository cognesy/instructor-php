# Instructor QA Scenarios

Use this file when the request needs concrete verification bundles or a deterministic multi-step QA plan.

## Fast Mapping

| Scenario | Minimum useful checks | Escalate when |
|----------|------------------------|---------------|
| Small implementation change in one package | `composer test` | shared interfaces, serializers, config, deserialization, or runtime wiring changed |
| Runtime, schema, serializer, deserializer, or config work | `composer test && composer qa` | dependency surface or workflow behavior changed |
| Docs or example edits | `composer qa:docs` | docs changed alongside runtime or package APIs |
| Dependency constraint change | `composer test && composer qa && act pull_request -W .github/workflows/php.yml -j build` | resolution is flaky, matrix cell fails, or unrelated repo debt blocks closure |
| Workflow or CI change | `act pull_request -W .github/workflows/php.yml -j build --dryrun` and at least one relevant non-dryrun cell | workflow matrix or environment assumptions changed across multiple lanes |
| Release-sensitive or cross-package work | `composer test-all && composer qa && composer qa:docs && act pull_request -W .github/workflows/php.yml -j build` | failures require triage or follow-up work |
| Performance-sensitive work | `composer bench` plus behavior checks | benchmark regression requires broader investigation |

## Tooling Lanes

### Tests

- Default fast path: `composer test`
- Broad path: `composer test-all`
- Opt-in live suite: `TELEMETRY_INTEROP_ENABLED=1 composer test:telemetry-interop`

### Static Analysis And Structure

- `composer qa:phpstan`
- `composer qa:psalm`
- `composer qa:pint`
- `composer qa:semgrep`
- `composer dead-code`
- `composer unused-deps`

### Docs

- `composer qa:docs`
- `composer docs drift`

### Local CI

- Full dryrun: `act pull_request -W .github/workflows/php.yml -j build --dryrun`
- Full local workflow: `act pull_request -W .github/workflows/php.yml -j build`
- Targeted matrix cell:

```bash
act pull_request \
  -W .github/workflows/php.yml \
  -j build \
  --matrix php:8.4 \
  --matrix symfony:^8.0 \
  --matrix composer:--prefer-stable \
  --matrix reflection_docblock:^6.0.3
```

Use `.actrc` defaults rather than inventing alternate local runner mappings.

## Complex QA Playbooks

### 1. Dependency Or Compatibility Change

Use a deterministic `bd` flow.

1. Create or claim a `bd` task.
2. Confirm the relevant Composer manifests and workflow matrix.
3. Run fresh dependency resolution if the bug is an install-time regression.
4. Run `composer test`.
5. Run `composer qa`.
6. Run the targeted `act` cell that matches the changed compatibility surface.
7. If unrelated repo debt blocks closure, create `discovered-from` follow-up tasks and record them in notes.

### 2. CI Failure Reproduction

1. Read the failing workflow and identify the exact job or matrix cell.
2. Reproduce with `act --dryrun`.
3. Run the smallest non-dryrun cell that matches the failure.
4. Compare local failure vs hosted CI behavior.
5. If the issue spans multiple causes, split triage and fixes into `bd` tasks.

### 3. Production-Readiness Or Release Audit

This is never a one-command task. Use `bd`.

Suggested phases:

1. Scope the release surface and risk lanes.
2. Run behavior and static-analysis gates.
3. Run docs QA when public surface changed.
4. Run local workflow reproduction with `act`.
5. Record blockers as current-regression vs pre-existing debt.
6. Close only when the remaining risk is explicit and tracked.

### 4. Mixed Current And Pre-Existing Failures

Do not treat all failures as part of the current change.

1. Identify which failures reproduce on untouched branches or unrelated files.
2. Keep the current task focused on the user-requested scope.
3. Create separate `bd` tasks for unrelated debt using `discovered-from`.
4. State clearly which verification lanes are blocked by that existing debt.
