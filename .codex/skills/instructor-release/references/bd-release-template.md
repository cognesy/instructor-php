# `bd` Release Template

Use this template to create a release epic and deterministic child tasks.

## Epic

Create one epic per release:

```bash
bd create "Release vX.Y.Z" -t epic -p 1 -d "Prepare and ship InstructorPHP vX.Y.Z with green preflight gates, verified examples, release notes, publish execution, and announcement handoff." --json
```

## Child Tasks

Create these child tasks under the epic:

1. Scope release and inventory changed packages since last tag
2. Run root preflight and fix failures until green
3. Run split-package verification and fix failures until green
4. Run curated live example smoke set and retry only failed examples
5. Summarize per-package changes from diffs
6. Audit docs/API drift and resolve release blockers
7. Write `docs/release-notes/vX.Y.Z.mdx`
8. Final publish gate and approval check
9. Execute `scripts/publish-ver.sh vX.Y.Z`
10. Draft release announcement and optional X posting handoff

## Suggested Dependency Chain

- `1` blocks `2`, `3`, `4`, `5`, `6`
- `5` and `6` block `7`
- `2`, `3`, `4`, and `7` block `8`
- `8` blocks `9`
- `9` blocks `10`

## Example Commands

```bash
bd create "Scope release vX.Y.Z and inventory changed packages" -t task --parent <epic-id> -p 1 --json
bd create "Run root preflight for vX.Y.Z and fix failures" -t task --parent <epic-id> -p 1 --json
bd create "Run split-package verification for vX.Y.Z" -t task --parent <epic-id> -p 1 --json
bd create "Run curated live examples for vX.Y.Z" -t task --parent <epic-id> -p 1 --json
bd create "Summarize per-package changes for vX.Y.Z" -t task --parent <epic-id> -p 2 --json
bd create "Audit docs and API drift for vX.Y.Z" -t task --parent <epic-id> -p 1 --json
bd create "Write release notes for vX.Y.Z" -t task --parent <epic-id> -p 1 --json
bd create "Final publish gate for vX.Y.Z" -t task --parent <epic-id> -p 1 --json
bd create "Publish vX.Y.Z" -t task --parent <epic-id> -p 1 --json
bd create "Draft announcement for vX.Y.Z" -t task --parent <epic-id> -p 2 --json
```

Then wire blockers with `bd dep`:

```bash
bd dep <scope-task-id> --blocks <root-preflight-id>
bd dep <scope-task-id> --blocks <split-tests-id>
bd dep <scope-task-id> --blocks <live-examples-id>
bd dep <scope-task-id> --blocks <package-summaries-id>
bd dep <scope-task-id> --blocks <docs-drift-id>
bd dep <package-summaries-id> --blocks <release-notes-id>
bd dep <docs-drift-id> --blocks <release-notes-id>
bd dep <root-preflight-id> --blocks <final-gate-id>
bd dep <split-tests-id> --blocks <final-gate-id>
bd dep <live-examples-id> --blocks <final-gate-id>
bd dep <release-notes-id> --blocks <final-gate-id>
bd dep <final-gate-id> --blocks <publish-id>
bd dep <publish-id> --blocks <announcement-id>
```

## Inventory Commands

Use the latest tag as the default release base:

```bash
latest_tag=$(git describe --tags --abbrev=0)
git diff --name-only "$latest_tag"..HEAD -- packages/*/src
```

List changed packages from source diffs:

```bash
git diff --name-only "$latest_tag"..HEAD -- packages/*/src | \
  sed -n 's|^packages/\\([^/]*\\)/src/.*|\\1|p' | sort -u
```

For a package-focused raw diff:

```bash
./scripts/release-notes-diff.sh <package-name>
```

Prefer direct diff inspection and Codex-written summaries. Do not require `scripts/release-notes-summary.sh` or `scripts/release-notes-all.sh`, because they depend on `claude` CLI.

## Notes Policy

Each task note should capture:

- exact commands run
- what passed
- what failed
- what was retried
- what remains blocked
- any `discovered-from` follow-up tasks for unrelated debt
