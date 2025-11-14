# Driver Architecture – Critical Feedback and Improvement Plan

## Summary

CurlNewDriver is a solid baseline, but there’s still duplication and a few rough edges that we can smooth out across all drivers. The primary opportunities are standardizing the orchestration template, centralizing exception mapping, and extracting request-factory concerns per framework. These changes will cut duplication, improve testability, and align with our code style (no deep nesting, early returns, no arrays as collections).

## Gaps vs. Proposed Standard

- Duplication of event dispatching and status validation across drivers.
  - Example duplication in CurlNewDriver: `dispatchRequestSent()`, `dispatchResponseReceived()`, `dispatchRequestFailed()` and `validateStatusCodeOrFail()` live inside the driver.
  - File: packages/http-client/src/Drivers/CurlNew/CurlNewDriver.php:133

- Error translation is embedded in drivers (match on curl error codes in CurlNew; message matching in framework drivers). This is brittle and repeated.

- No shared template method for the “handle() → perform → build → validate → dispatch” flow, so each driver re-implements control flow and error mapping.

## Concrete Improvements

1) Introduce AbstractDriver base (Template Method)
- Purpose: Centralize orchestration, event dispatch, and status validation.
- Impact: Remove 50–70 lines from each driver; consistent error and event behavior.
- Sketch:
  - `src/Drivers/AbstractDriver.php` defines final `handle(HttpRequest): HttpResponse` and protected hooks:
    - `performHttpCall()`
    - `buildHttpResponse()`
    - `handleDriverException()`
    - `createDefaultClient()` and `validateClientInstance()`

2) Exception mappers per framework/driver
- Purpose: Replace string matching scattered across drivers with focused mappers.
- CurlNew: `CurlErrorMapper` to map curl errno to domain exceptions.
- Frameworks: `SymfonyExceptionMapper`, `GuzzleExceptionMapper`, `LaravelExceptionMapper` wrap framework types/messages.
- Benefit: Single responsibility and easier testing.

3) Request factories per framework
- Purpose: Encapsulate request/option preparation that is currently inline.
- Files to add (examples):
  - `src/Drivers/Symfony/SymfonyRequestFactory.php`
  - `src/Drivers/Laravel/LaravelRequestFactory.php`
  - `src/Drivers/Guzzle/GuzzleRequestFactory.php`
- Reuse between driver and pool to eliminate duplication.

4) Small refactors in CurlNewDriver for cleanliness
- Replace inline curl error mapping with `CurlErrorMapper` (shared with pool).
- Keep only orchestration in the driver – all mechanics in factory, adapters, mappers.

## Specific Code Pointers

- Centralize status validation and events (remove from driver):
  - packages/http-client/src/Drivers/CurlNew/CurlNewDriver.php:133
  - packages/http-client/src/Drivers/CurlNew/CurlNewDriver.php:158
  - packages/http-client/src/Drivers/CurlNew/CurlNewDriver.php:167
  - packages/http-client/src/Drivers/CurlNew/CurlNewDriver.php:175

- Replace inline curl error mapping with mapper:
  - packages/http-client/src/Drivers/CurlNew/CurlNewDriver.php:145

## Alignment with AGENTS.md

- Use strict types and type hints – already in place; keep it consistent.
- Avoid deep nesting – Template Method reduces nesting per driver.
- Avoid arrays as collections – factories/mappers are explicit objects.
- Prefer immutability – factories/mappers are stateless or readonly.

## Suggested Roadmap (Driver-side)

- Phase 1: Add `AbstractDriver` + tests; move event dispatch + status validation.
- Phase 2: Add `DriverExceptionMapper` + per-driver mappers; add tests.
- Phase 3: Add request factories for Symfony/Laravel/Guzzle; refactor drivers to use them.
- Phase 4: Update CurlNewDriver to use `CurlErrorMapper`; remove inline mapping and dispatch boilerplate.

## Expected Outcomes

- ~60% driver code reduction versus current duplication baseline.
- Consistent, testable error semantics across drivers.
- Cleaner, smaller driver classes acting purely as orchestrators.

