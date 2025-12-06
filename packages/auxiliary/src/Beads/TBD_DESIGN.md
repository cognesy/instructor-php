# `tbd` Design (Beads-lite CLI for constrained environments)

## Purpose
- Provide a Beads-like workflow via Symfony Console commands when `bd` binaries cannot be installed.
- Operate directly on Beads-compatible JSONL files (deterministic, diffable) using existing `packages/auxiliary/src/Beads/FileFormat/Jsonl` DTOs/services.
- Keep git operations out of scope (developers can use raw git commands).

## Constraints
- No external binaries; PHP + Symfony Console only (no Laravel/Artisan runtime).
- File-first storage (JSONL). Eloquent migrations exist but are out of scope unless explicitly enabled later.
- Deterministic or user-specified IDs/timestamps must be supported; avoid hidden randomness when reproducibility is required.
- Must remain compatible with existing Beads JSONL schema (`IssueDTO`, `DependencyDTO`, `CommentDTO`, enums).

## Existing Assets to Reuse
- `BeadsJsonlFileService` for load/save of issues.
- `JsonlParserService` / `JsonlGeneratorService` for parsing/serializing JSONL.
- DTOs/enums for status, priority, issue type, dependencies, comments.
- Docs reference: `docs-internal/bd-bv/*` for command shape/terminology.

## Non-Goals
- Graph/visualization features (can be added later).
- Automating git sync/branching; users use git directly.
- Replacing LLM-assisted content generation—CLI focuses on deterministic operations; agents/LLMs can still draft descriptions/notes.

## CLI Surface (`tbd <command>`) — flat verbs to mirror `bd`
Commands modeled after common `bd` flows (no `tbd:*` namespaces):
- `tbd init --file=<path>`: create an empty JSONL file if missing; validate schema.
- `tbd list --file=<path> [--status=<open|in_progress|closed|blocked>] [--limit=N] [--assignee=...] [--label=...]`
- `tbd ready --file=<path> [--limit=N]`: tasks with no blockers (no unresolved dependencies).
- `tbd show <id> --file=<path> [--json]`
- `tbd create --file=<path> --title=... --type=<task|bug|epic|story> --priority=<0-3> [--description=...] [--labels=csv] [--assignee=...] [--id=...] [--created-at=...] [--external-ref=...]`
- `tbd update <id> --file=<path> [--title=...] [--description=...] [--status=...] [--priority=...] [--assignee=...] [--labels=csv] [--acceptance=...] [--notes=...] [--estimate-min=...] [--close-reason=...] [--updated-at=...]`
- `tbd close <id> --file=<path> [--reason=...] [--closed-at=...]` (status -> closed, set timestamps)
- `tbd comment <id> --file=<path> --author=... --body=... [--created-at=...]`
- `tbd dep add <id> --file=<path> --on=<blocking-id> --type=<blocks|relates>` (write dependency edge)
- `tbd dep rm <id> --file=<path> --on=<blocking-id>`
- `tbd dep tree <id> --file=<path> [--direction=<up|down|both>]` (textual tree)
- `tbd compact --file=<path>` (optional) to re-write JSONL sorted by id, recompute `compaction_level/original_size` metadata if needed).

Executable packaging:
- Ship `packages/auxiliary/bin/tbd` with `#!/usr/bin/env php`, register via Composer `bin` so `vendor/bin/tbd ...` works; devs can symlink to PATH for a first-class `tbd` command.

## Data & Determinism Requirements
- Default ID: UUIDv4 unless `--id` supplied; provide `--id-seed` for deterministic UUIDv5 if desired.
-.timestamps: default `now()` UTC; allow overrides (`--created-at`, `--updated-at`, `--closed-at`).
- When generating content (description/notes), users/LLMs supply text; CLI only validates and writes.
- Preserve unknown fields when round-tripping? JSONL is generated from DTOs; document that only schema fields are persisted.

## UX / Output
- Human-readable table output by default; `--json` for scriptability.
- Return non-zero exit codes on validation errors or missing records.
- File path always explicit via `--file`; no hidden globals.
- Support `--dry-run` on mutating commands (show diff/summary without writing).
- Optional `.tbdconfig` (future) to set default JSONL path to reduce `--file` verbosity while keeping explicit control available.

## Architecture Sketch
- Controller layer: Symfony Console commands in `packages/auxiliary/src/Beads/Presentation/Console/Tbd*Command.php` (future adapters could include Artisan or HTTP). Controllers only parse IO and delegate.
- Action layer: invokable PHP actions per verb (init, list, ready, show, create, update, close, comment, dep, compact) under `packages/auxiliary/src/Beads/Application/Tbd/`. Actions accept DTOs/options, invoke services, and remain transport-agnostic for reuse across CLI/web.
- Services: wrap `BeadsJsonlFileService` for query/mutate/format operations.
- Dependency checks leverage `IssueDTO.dependencies` using `DependencyTypeEnum`.
- All writes go through `JsonlGeneratorService::writeFile` (atomic-ish); consider temp-file swap later for stronger guarantees.

## AI/LLM Integration Guidance (parity with bd patterns)
- Keep writes deterministic (ids, timestamps, statuses, dependencies) while allowing an opt-in “suggest/autofill” step for text fields (title/description/acceptance/notes/comments) via an LLM. Persist the generated text to JSONL only after confirmation.
- Possible CLI affordances: `tbd create ... --suggest` or a separate `tbd suggest` that emits drafted fields (JSON) for review before calling `create/update`.
- Planning/graph insights like `bv --robot-plan` are out of scope initially; a future `tbd plan` could call an LLM/algorithm to propose ordering based on dependencies.
- Updates/close summaries: allow an optional `--summarize-from=<path or stdin>` that feeds logs/diffs to the LLM, returning a concise note; write only after confirmation.
- Separation of concerns: LLM calls live outside the action layer; actions accept already-prepared text. This keeps the action layer deterministic and reusable in environments without network/LLM.

## Validation Rules
- Required: id, title, description (allow empty?), status, priority, issue_type, timestamps per DTO invariants.
- Close invariant: status=closed => closed_at set; non-closed => closed_at null.
- Dependency types limited to enum; prevent self-dependency.
- Labels deduped; priorities within 0–3.

## Open Questions / Future Work
- Add `tbd:sync` hooks for git? (out of scope now)
- Add `tbd:graph` JSON export for graph tooling?
- Support Eloquent store behind a feature flag?
- Pluggable ID/timestamp provider for stricter determinism?

## Deliverables
- This design doc.
- Console command stubs + service layer under `packages/auxiliary/src/Beads` (future work).
- Usage doc (README update) with examples mirroring `bd` quick-reference.
