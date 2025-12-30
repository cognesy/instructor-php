# tbd: Beads-like CLI for JSONL tasks (bd-compatible format)

`tbd` is a PHP/Symfony Console CLI that mirrors common `bd` workflows but operates on Beads JSONL files locally (no bd/bv binaries needed). Actions are deterministic (ids/timestamps) with optional LLM “suggest” steps left to external tools.

## Install / Run
- From repo: `vendor/bin/tbd <command>`
- Make executable in PATH (optional): `chmod +x packages/auxiliary/bin/tbd && ln -s $(pwd)/packages/auxiliary/bin/tbd /usr/local/bin/tbd`
- Files: always specify `--file <path>` (or set a future `.tbdconfig` default)

## Core commands (flat verbs like bd)
- `tbd init --file <path>`: create empty JSONL
- `tbd list --file <path> [--status open|in_progress|blocked|closed] [--assignee …] [--label …] [--limit N]`
- `tbd ready --file <path> [--limit N]`: unblocked items (no deps)
- `tbd show <id> --file <path> [--json]`
- `tbd create --file <path> --title … --description … [--type task|bug|epic|story|feature|chore] [--priority 0-4] [--status …] [--assignee …] [--labels …] [--id …] [--created-at …] [--external-ref …] [--acceptance …] [--notes …] [--estimate-min …] [--design …]`
- `tbd update <id> --file <path> [--title …] [--description …] [--status …] [--priority …] [--assignee …] [--labels …] [--acceptance …] [--notes …] [--estimate-min …] [--close-reason …] [--updated-at …] [--design …] [--external-ref …]`
- `tbd close <id> --file <path> [--reason …] [--closed-at …]`
- `tbd comment <id> --file <path> --author … --text … [--created-at …]`
- `tbd dep add <id> --file <path> --on <blocking-id> [--type blocks|related|parent-child|discovered-from] [--created-by …] [--created-at …]`
- `tbd dep rm <id> --file <path> --on <blocking-id> [--type …]`
- `tbd dep tree <id> --file <path> [--direction up|down|both]` (direct edges)
- `tbd compact --file <path>`: sort by id, rewrite file

## Data model & determinism
- Storage: Beads JSONL schema (IssueDTO + DependencyDTO + CommentDTO + enums).
- IDs: UUIDv4 by default; `--id` to override (deterministic seeds can be added later).
- Timestamps: default UTC now; overridable via CLI options.
- Outputs: tables by default; `--json` on `show` for scriptability; `--dry-run` planned for mutators.

## Layering
- Controller: Symfony Console commands (transport only).
- Actions: invokable, transport-agnostic PHP classes under `packages/auxiliary/src/Beads/Application/Tbd/`.
- Services: existing JSONL parser/generator and DTOs.

## AI/LLM guidance (optional “suggest”)
- Keep `tbd` writes deterministic: ids, timestamps, statuses, deps, and any text you pass in. Let the agent/LLM generate drafts separately, then supply finalized text to `tbd create/update/comment`.
- Expected agent flow:
  1) Gather raw spec (user request, TODO, bug note).
  2) Use LLM to draft human-readable fields (title, description, acceptance criteria, notes, close summaries) outside of `tbd`.
  3) Review/trim for determinism; remove hallucinated metadata; keep only the intended text.
  4) Call `tbd create/update/comment` with those finalized strings. Avoid embedding execution logs or unstable data unless intentional.
- Optional future: a `tbd suggest` helper could emit a draft JSON (not committed) for review, but persistence must still go through the standard deterministic commands.

## Quick start
```bash
tbd init --file .beads/tasks.jsonl
tbd create --file .beads/tasks.jsonl --title "Investigate latency" --description "..." --type bug --priority 1
tbd list --file .beads/tasks.jsonl --status open
tbd dep add TASK-2 --file .beads/tasks.jsonl --on TASK-1
```
