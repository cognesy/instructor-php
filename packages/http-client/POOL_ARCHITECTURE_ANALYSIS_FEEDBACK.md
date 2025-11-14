# Pool Architecture – Critical Feedback and Improvement Plan

## Summary

CurlNewPool’s functionality is correct and performant, but its current structure mixes too many responsibilities in one class and uses arrays-as-collections heavily. This hurts readability, testability, and violates our style guidance. We can make it much cleaner by introducing small dedicated objects (state, collections, error mapper), extracting the multi-loop runner, and sharing common pool logic via a base class.

## Key Issues in CurlNewPool

- Arrays used as collections and tuple-like payloads.
  - Active transfer context is an array keyed by `spl_object_id` with `['handle','parser','request','index']`.
  - File: packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:96
- Excessive parameter passing and mutation-by-reference across methods.
  - `fillWindow()`, `driveMultiHandle()`, `processCompleted()` each pass multiHandle, queue, queueIndex (by ref), maxConcurrent, active (by ref), responses (by ref).
  - Files: packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:100, 136, 167
- Event dispatching and status validation duplicated vs. driver.
  - Files: packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:324, 353
- Multi loop robustness is basic and does not handle known cURL multi edge-cases.
  - No handling for `CURLM_CALL_MULTI_PERFORM`.
  - `curl_multi_select()` can return `-1`; best practice is to `usleep(…)/curl_multi_wait()` to avoid busy loops.
  - Files: packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:145, 154
- Inline curl error mapping; not shared with the driver.
  - File: packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:301

## Proposed Design (Cleaner, Smaller, Testable)

1) Introduce small, explicit objects (no arrays-as-collections)
- ActiveTransfer (value object)
  - Fields: `CurlHandle handle`, `HeaderParser parser`, `HttpRequest request`, `int index`.
- ActiveTransfers (collection)
  - Methods: `add(ActiveTransfer)`, `getByNativeHandle(CurlHandle|CurlHandle native)`, `removeByHandle(CurlHandle)`, `count()`.
- Responses (collection)
  - Methods: `set(int index, Result)`, `finalize(): array<Result>` – guarantees stable order.

2) Extract pool state and runner
- PoolState (mutable, but scoped)
  - Fields: `queue`, `nextIndex`, `maxConcurrent`, `activeTransfers`, `responses`.
- CurlMultiRunner (owns the event loop)
  - Methods:
    - `fillWindow(State)`
    - `tick(State)` – drives `curl_multi_exec`, processes `curl_multi_info_read` messages
    - `wait(State)` – wraps `curl_multi_select` with `curl_multi_wait`/backoff for `-1`
  - Isolated and directly unit-testable with mocks/doubles.

3) Centralize error mapping and common behaviors
- CurlErrorMapper – maps `curl_errno()` to domain exceptions.
- AbstractPool – shared base for:
  - `dispatchRequestSent`, `dispatchResponseReceived`, `dispatchRequestFailed`
  - `validateStatusCodeOrFail()`
  - `normalizeResponses()`

4) Tighter multi-loop semantics (robustness)
- Handle `CURLM_CALL_MULTI_PERFORM` by continuing the loop instead of `break`.
- When `curl_multi_select()` returns `-1`, use a short `usleep(1000)` or `curl_multi_wait()` as fallback to avoid CPU spin.
- Drain all messages from `curl_multi_info_read()` before waiting again.

## What the Refactor Looks Like

- CurlNewPool becomes a small orchestrator that wires:
  - `CurlFactory` (already present)
  - `CurlErrorMapper` (new)
  - `CurlMultiRunner` (new)
  - `ActiveTransfers` and `Responses` (new)
- The methods `fillWindow`, `driveMultiHandle`, `processCompleted`, `attachRequest`, `detachHandle` collapse into a thin delegation to `CurlMultiRunner`, removing the by-ref parameter plumbing.
- Event dispatch and status validation come from `AbstractPool`.

## Concrete Code Pointers to Improve

- Replace tuple-arrays with value objects/collections:
  - packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:96
  - packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:231
- Remove by-ref plumbing via state+runner extraction:
  - packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:100, 136, 167
- Use shared error mapper instead of inline mapping:
  - packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:301
- Move status validation + event dispatch to base class:
  - packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:324
  - packages/http-client/src/Drivers/CurlNew/CurlNewPool.php:353

## Minimal API-Safe Refactor Plan

- Phase 1: Introduce support classes (no behavior change)
  - Add: `src/Drivers/CurlNew/ActiveTransfer.php`, `ActiveTransfers.php`, `Responses.php`
  - Add: `src/Drivers/CurlNew/CurlErrorMapper.php`
  - Add: `src/Drivers/AbstractPool.php` with event/status helpers
- Phase 2: Extract runner
  - Add: `src/Drivers/CurlNew/CurlMultiRunner.php` (depends on above types)
  - Update: `CurlNewPool` to delegate to runner and base-class helpers
- Phase 3: Robustify the loop
  - Implement handling for `CURLM_CALL_MULTI_PERFORM`
  - Add fallback for `curl_multi_select() === -1` using `curl_multi_wait()` or `usleep(…)`
- Phase 4: Align driver and pool mappers
  - Update `CurlNewDriver` to use the shared `CurlErrorMapper`

## Example Interfaces (sketch)

```php
final class ActiveTransfer {
    public function __construct(
        public readonly CurlHandle $handle,
        public readonly HeaderParser $parser,
        public readonly HttpRequest $request,
        public readonly int $index,
    ) {}
}

final class ActiveTransfers {
    /** @var array<int, ActiveTransfer> */
    private array $byId = [];
    public function add(ActiveTransfer $t): void { $this->byId[spl_object_id($t->handle->native())] = $t; }
    public function getByNativeHandle($native): ?ActiveTransfer { return $this->byId[spl_object_id($native)] ?? null; }
    public function removeByHandle(CurlHandle $h): void { unset($this->byId[spl_object_id($h->native())]); }
    public function count(): int { return count($this->byId); }
}

final class Responses {
    /** @var array<int, Result> */
    private array $items = [];
    public function set(int $index, Result $r): void { $this->items[$index] = $r; }
    /** @return array<Result> */
    public function finalize(): array { ksort($this->items); return array_values($this->items); }
}
```

## Alignment with AGENTS.md

- Avoid arrays as collections – replaced with small typed classes.
- Low nesting – runner encapsulates the loop, pool orchestrator stays flat.
- Prefer immutability – value objects for transfers; classes expose minimal mutation.
- Early returns – preserved in helpers and base.

## Expected Outcomes

- Clear separation of concerns (runner vs. orchestration vs. mapping).
- Simpler CurlNewPool (fewer methods, parameters, and refs).
- Easier unit testing (isolated runner + collections + mappers).
- More robust multi loop under edge conditions.

