# Messages Refactor – Dev Team Instructions

Goal
- Finish the Messages API refactor by fully adopting value objects (ContentParts, MessageList) while keeping a backwards-compatible surface (deprecated array accessors), and ensure the canonical nested content-part shape is the only output format.
- Ensure every change is minimal (YAGNI), immutable, and uses strict types. Avoid multi-level control flow, prefer early returns and `match` when branching.

Scope
- Primary package: `packages/messages`
- Known downstream usages: `packages/templates`, `packages/polyglot`, and any addons/tests that use Messages/Content.
- Use Pest for tests. Static analysis (PHPStan/Psalm) should remain happy.

Non‑negotiables (style + design)
- Use value objects over raw arrays for collections (ContentParts, MessageList).
- Keep API immutable; all “mutations” must return new instances.
- Keep deprecated methods callable but minimize usage in code/tests.
- Use `match` for branching in new code; avoid ternary chains and `switch`.
- Prefer early returns over nested control flow.

Canonical content-part shape (the invariant)
- Output MUST be nested for non-text parts:
  - image: `['type' => 'image_url', 'image_url' => ['url' => ...]]`
  - audio: `['type' => 'input_audio', 'input_audio' => ['data' => ..., 'format' => ...]]`
  - file: `['type' => 'file', 'file' => ['file_data' => ..., 'file_name' => ..., 'file_id' => ...]]`
- Legacy flat inputs are accepted, but normalization happens on output.

Current refactor baseline (what should already exist)
- `ContentParts` value object in `packages/messages/src/ContentParts.php`.
- `MessageList` value object in `packages/messages/src/MessageList.php`.
- `Content` stores `ContentParts` internally and exposes `partsList()`; `parts()` exists but is deprecated.
- `Messages` stores `MessageList` internally and exposes `messageList()`; `all()`, `head()`, `tail()` exist but are deprecated; `headList()/tailList()` exist.
- `ContentInput`, `MessageInput`, `MessagesInput` accept and normalize `ContentParts` and `MessageList`.
- Templates/Anthropic format updated to iterate `contentParts()->all()` / `messageList()->all()`.
- Docs in `packages/messages/README.md` and `packages/messages/CHEATSHEET.md` mention ContentParts/MessageList + deprecations.

If any of the above is missing, add it first before proceeding.

Step-by-step completion plan

1) Audit and eliminate deprecated collection access in the codebase
- Replace `Messages::all()` usages with `messageList()->all()` or `messageList()->count()` in internal code and tests.
- Replace direct `->all()[index]` access with `messageList()->get(index)` (ensure `MessageList::get(int): ?Message` exists).
- For content parts, keep `contentParts()->all()` where a raw array is needed for iteration. Prefer `ContentParts` methods for counts/filters.
- Recommended search patterns:
  - `rg -- "->all\(\)" packages`
  - `rg -- "all\(\)\[" packages`

2) Normalize Messages internals to MessageList
- Ensure `Messages` methods use `$this->messages->all()` internally (not `$this->all()`), so we can safely deprecate `all()`.
- Keep `Messages::all()` as deprecated compatibility only.
- Ensure conversions (`toArray`, `toMergedPerRole`, `filter`, etc.) are based on `MessageList` and `ContentParts`.

3) Harden Content/ContentParts behavior
- Verify `Content::fromParts(ContentParts $parts)` exists and `Content::partsList()` returns `ContentParts`.
- Ensure `ContentInput` accepts arrays of parts, single part, `ContentParts`, `ContentPart`, and message arrays with nested content.
- Ensure `ContentParts` has the minimal utility surface: `all()`, `count()`, `isEmpty()`, `first()`, `last()`, `get(index)`, `add()`, `replaceLast()`, `filter()`, `map()`, `reduce()`, `withoutEmpty()`, `toArray()`, `toString()`.

4) Update tests for the new collection types
- All tests should assert via `messageList()->count()` or `messageList()->get()` rather than `all()`.
- Where a list instance is required, assert `messageList()` returns a `MessageList` object.
- Add/adjust tests for `MessageList::get()`.
- Ensure composite message tests use `ContentParts` in expectations.

5) Documentation
- README: clearly state canonical nested content-part shape, use of `ContentParts`, `MessageList`, and deprecations.
- CHEATSHEET: include `MessageList::get()` and `isEmpty()` in the API summary; keep deprecations listed.

6) Run tests and validate
- Run Pest for messages package.
- Run any downstream tests that rely on Messages (templates/polyglot/addons) if available.
- Verify no residual deprecated usages in core packages.

Commands (suggested)
- Search deprecated usages:
  - `rg -- "->all\(\)" packages`
  - `rg -- "all\(\)\[" packages`
- Run tests:
  - `vendor/bin/pest packages/messages`
  - Optional: `vendor/bin/pest packages/templates packages/polyglot`

Expected outputs
- All tests pass.
- No production code in `packages/messages`, `packages/templates`, or `packages/polyglot` uses deprecated `Messages::all()` or array indexing from it.
- `messageList()` is the canonical way to access message collections in code/tests.
- Content parts always serialize in the nested canonical shape.

Notes
- Keep backwards compatibility by leaving deprecated methods in place; do not remove them in this phase.
- Do not expand API beyond what is needed to complete the refactor.
- Follow strict types and immutability everywhere.
