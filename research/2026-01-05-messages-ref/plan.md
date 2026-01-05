# Messages Refactor Plan (2026-01-05)

Goal: simplify `packages/messages` with minimal behavior changes, stepwise risk reduction, and clear rollback points.

## Phase 0 — Baseline + Guardrails

### Outcomes
- Known behavior documented for key parsing/serialization paths.
- Minimal test safety net for the refactor.

### Steps
1. Add targeted Pest tests:
   - `Message::fromArray`, `Message::fromAny`, `Message::toArray`.
   - `Messages::fromAnyArray`, `Messages::toArray`, `Messages::toString` composite guard.
   - `Content::fromAny`, `Content::normalized`, `Content::toString`.
2. Capture any current quirks in tests (e.g., how associative arrays are treated in `Message::fromArray`).

### Files
- `packages/messages/src/Message.php`
- `packages/messages/src/Messages.php`
- `packages/messages/src/Content.php`
- `packages/messages/tests/*`

### Exit Criteria
- Tests pass and reflect current behavior (even if inconsistent).

---

## Phase 1 — Input Normalization Consolidation

### Outcomes
- A single, reusable normalization path for message/content inputs.
- Reduced duplication across constructors and factories.

### Steps
1. Introduce a small internal normalizer (e.g., `MessageInput` + `MessagesInput` + `ContentInput` or a shared `InputNormalizer`).
2. Move parsing logic out of:
   - `Message::fromAny`, `Message::fromArray`, `Message::fromInput`.
   - `Messages::fromAnyArray`, `Messages::fromArray`, `Messages::fromInput`.
   - `Content::fromAny`.
3. Update the original methods to delegate to the new normalizer.
4. Keep public signatures unchanged.

### Files
- `packages/messages/src/Message.php`
- `packages/messages/src/Messages.php`
- `packages/messages/src/Content.php`
- New internal normalizer class under `packages/messages/src/Support/` (name TBD)

### Exit Criteria
- Tests still pass.
- Core parsing logic lives in one place.

---

## Phase 2 — Canonical ContentPart Shape

### Outcomes
- One consistent output structure for content parts.
- Compatibility with both current input shapes.

### Canonical shape (emit)
```php
// text
['type' => 'text', 'text' => 'hello']

// image
['type' => 'image_url', 'image_url' => ['url' => 'https://...', 'detail' => 'high']]

// audio
['type' => 'input_audio', 'input_audio' => ['data' => '...base64...', 'format' => 'wav']]

// file
['type' => 'file', 'file' => ['file_data' => 'data:...base64...', 'file_name' => 'report.pdf', 'file_id' => 'file-...']]
```

### Steps
1. Document the canonical nested shape above in `README.md`/`CHEATSHEET.md`.
2. Update `ContentPart::fromArray()` to accept both legacy and canonical shapes.
3. Update `ContentPart::*` factory methods and `Utils/*::toContentPart()` to emit canonical shape.
4. Ensure `Content::normalized()` and `Message::toArray()` use the canonical output.

### Files
- `packages/messages/src/ContentPart.php`
- `packages/messages/src/Utils/Image.php`
- `packages/messages/src/Utils/File.php`
- `packages/messages/src/Utils/Audio.php`
- `packages/messages/src/Content.php`

### Exit Criteria
- Tests pass.
- Content parts are consistently shaped when serialized.

---

## Phase 3 — Role Helpers + Collection Simplification

### Outcomes
- Reduced duplication in role-specific methods.
- More predictable collection operations.

### Steps
1. Add a single role-aware append helper in `Messages` and delegate `asSystem/asUser/...` to it.
2. Add a single role-aware constructor helper in `Message` and delegate `asSystem/asUser/...` to it.
3. Simplify `Messages::filter()` to have predictable behavior when callback is null.
4. Reduce repeated loops by using shared helpers (e.g., internal append/normalize). Keep immutability.

### Files
- `packages/messages/src/Message.php`
- `packages/messages/src/Messages.php`

### Exit Criteria
- Tests pass.
- No public API changes.

---

## Phase 4 — Error Handling Clarification

### Outcomes
- Clearer behavior when roles/content are invalid.
- Optional strictness without breaking existing call sites.

### Steps
1. Add `MessageRole::tryFromString()` (Result or nullable) for strict usage.
2. Update the normalizer to use `tryFromString()` with a safe fallback.
3. Keep `fromString()` behavior for backward compatibility.

### Files
- `packages/messages/src/Enums/MessageRole.php`
- Normalizer class under `packages/messages/src/Support/`

### Exit Criteria
- Tests pass.
- Invalid roles are handled consistently.

---

## Phase 5 — Small Hygiene + Documentation

### Outcomes
- Cleaned up low-risk issues and clarified docs.

### Steps
1. Fix base64 error message formatting in `Image::fromBase64()` and `File::fromBase64()`.
2. Update `packages/messages/README.md` and/or `CHEATSHEET.md` for new canonical content shapes.
3. Add migration notes for any behavior quirks if changed.

### Files
- `packages/messages/src/Utils/Image.php`
- `packages/messages/src/Utils/File.php`
- `packages/messages/README.md`
- `packages/messages/CHEATSHEET.md`

### Exit Criteria
- Tests pass.
- Documentation reflects current behavior.

---

## Sequencing Notes
- Each phase should be a separate PR/commit.
- Run tests after each phase to confirm no behavior regressions.
- If behavior changes are required, add them in a dedicated phase with explicit tests.

## Optional Stretch
- Introduce dedicated value objects for `ContentParts` or `MessageList` if the team agrees, but only after the above simplifications are stable.
