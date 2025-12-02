# Review: PHP bd/bv Integration Spec (IMPLEMENTATION-SPEC.md)

- Date: 2025-12-01
- Reviewer: AI Agent
- Scope: research/studies/2025-12-php-bd-api/IMPLEMENTATION-SPEC.md

## Findings
1) ID format mismatch (332-338): `TaskId` regex hard-codes `bd-...`, but this repo uses hash IDs with project prefix (`partnerspot-xxx`, per bd/bv docs). Spec will reject real IDs and break round-trips.
2) Status model gap (393-420, 300-305): Domain statuses omit `blocked` even though bd workflows rely on it; a `block()` method exists but no `Blocked` state, so blocked issues cannot be represented or enforced.
3) CLI contract undefined (537-563): Repository/CLI wrapper assumes a custom `BdClient::update($data)` but does not map to real bd CLI flags (`bd create/update/close/dep add ... --json`, type prefixes, priorities, reasons). Persistence semantics are unspecified.
4) Command execution safety (841-857, 875-881): Host executor runs binaries with user-supplied titles/descriptions but no escaping/validation plan; shell injection risk unless encoded arguments or safe proc invocation is defined.
5) Testing fragility (751-799): Integration tests invoke real `bd init`/CLI in temp dirs; in CI/sandboxes without binaries or with restricted networking, tests will fail/flake. No fake executor or fixture-backed repository is specified.
6) Config alignment (834-858): Config defaults to system paths and ignores project-required env (e.g., sandbox `BD_NO_DB`, hook-managed binaries). Risk of pointing to the wrong binary or failing in constrained environments.
7) Execution scope (875-900): Service provider exposes executor/repositories but does not constrain usage to CLI/agent contexts; web/API invocation could run bd/bv with untrusted input unless explicitly blocked.

## Open Questions
- What is the canonical ID pattern to accept (current `partnerspot-*` hash IDs, future length scaling)?
- How should domain statuses map to bd’s real states, including `blocked` and close reasons?
- Should bd/bv calls be limited to CLI/agent contexts, and what escaping/auth boundaries apply if exposed via web?
- Do we need a fake executor for tests/CI with fixture JSONL instead of real binaries?
- How should config locate binaries (hook-managed path vs system) and honor required env flags?
