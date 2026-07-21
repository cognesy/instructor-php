# HTTP Record/Replay Redesign

**Status:** accepted planning baseline, peer-reviewed against the live implementation  
**Epic:** `instructor-jpma`  
**Date:** 2026-07-21

## Goal

Turn `packages/http-client` record/replay from a request-keyed response cache into a
safe, deterministic cassette facility without making the common path cumbersome.
Recording must preserve streaming latency, replay must be hermetic by default, and
both paths must remain bounded in PHP memory for long streams.

The primary consumer is the examples suite, but the design remains an HTTP-client
capability rather than examples-specific infrastructure.

## Constraints

- Keep `PendingHttpResponse` unchanged.
- Preserve the existing progressive recording behavior: chunks reach the caller
  before the upstream stream completes, and partial/failed streams are not finalized
  as successful recordings.
- Do not retain 50K-100K streamed chunks in PHP arrays during recording or replay.
- Make replay safe for test/CI use: a miss must not silently make a live request.
- Support repeated identical requests with different responses, including retry
  scenarios.
- Fixtures may contain arbitrary/binary response bytes and must fail loudly when
  malformed or incompatible.
- Preserve a concise default API while making matcher, sanitizer, store, and event
  behavior replaceable through explicit contracts.
- Provide a compatibility/deprecation path for the current public classes.

## Current evidence

The live implementation has useful foundations, but the aggregate contract is not
robust:

1. `RequestRecords` writes one file for `(streamed, fingerprint)`, so a second
   identical request overwrites the first response. There is no cassette/session or
   replay cursor.
2. `ReplayMiddleware` and `RecordReplayMiddleware` default
   `fallbackToRealRequests` to `true`; a stale or missing fixture can escape to the
   network.
3. `RequestRecord::fromJson()` accepts weakly shaped arrays and response accessors
   default missing fields to HTTP 200 and an empty body. `toJson()` converts encoding
   failure into an empty string.
4. Synchronous writes target the final path directly without a lock or atomic rename.
5. `ExactHashMatcher` hashes raw `method|url|body`, ignores response-shaping headers,
   uses a credential query list that has already diverged from the redactor, and is
   not injectable through the middleware constructors.
6. `saveStreamed()` redacts each chunk independently and keeps only the first output
   chunk. A secret split across chunk boundaries can leak or the transformed response
   can be truncated.
7. Stream recording is progressive, but replay decodes the entire JSON fixture into a
   `list<string>` and wraps it in `ArrayStream`. A 100K-chunk probe allocated roughly
   8.4 MB to replay a roughly 400 KB fixture.
8. Record/replay events expose raw requests/responses or raw URLs, creating a second
   sensitive-data egress path.
9. The documented surface contains three mutable middleware classes, string modes,
   setters that mutate lazily created delegates, and `getRecords()` exposing the
   persistence implementation.

The previous epics `instructor-a214` and `instructor-jzmw` established ambient
examples integration, request redaction, owner-only files, and progressive stream
recording. This redesign builds on those gains rather than reverting them.

## Proposed public surface

Document one middleware and make the safe/common choices named constructors:

```php
use Cognesy\Http\Extras\Middleware\RecordReplay\RecordReplayMiddleware;

$recording = RecordReplayMiddleware::recordTo(
    directory: __DIR__ . '/recordings',
);

$replaying = RecordReplayMiddleware::replayFrom(
    directory: __DIR__ . '/recordings',
); // hermetic: a miss throws and never reaches the network
```

There is no `pass` mode: callers simply do not attach the middleware. This removes a
mode that performs no work and avoids stringly typed configuration in normal PHP
usage.

Advanced behavior is immutable and explicit:

```php
use Cognesy\Http\Extras\Support\RecordReplay\RecordReplayPolicy;
use Cognesy\Http\Extras\Support\RecordReplay\ReplayMissPolicy;

$replaying = RecordReplayMiddleware::replayFrom(
    directory: __DIR__ . '/recordings',
    policy: new RecordReplayPolicy(
        matcher: $matcher,
        sanitizer: $sanitizer,
        onMissing: ReplayMissPolicy::Passthrough,
    ),
);
```

`Passthrough` is deliberately visible at the call site. The default is
`ReplayMissPolicy::Fail`.

For non-filesystem storage, the middleware should also accept a `CassetteStore`
through an advanced named constructor (`recordWith()` / `replayWith()`) or an
equally concise final spelling chosen by the public-contract task. The convenience
constructors delegate to `FilesystemCassetteStore`.

Public API rules:

- `RecordReplayMiddleware` is `final`, immutable, and the only documented middleware.
- `RecordReplayPolicy` is a `final readonly class` with safe defaults.
- `ReplayMissPolicy` is a string-backed enum.
- `RequestMatcher`, `FixtureSanitizer`, and `CassetteStore` are the supported extension
  seams. They must be injectable through the documented middleware API.
- `RecordingMiddleware`, `ReplayMiddleware`, mutable setters, `getRecords()`,
  `RequestRecords`, `RequestRecord`, and `StreamedRequestRecord` become deprecated
  compatibility or internal implementation surfaces, with a major-version removal
  path.
- Events contain typed, sanitized metadata DTOs; they never carry raw request or
  response objects.

The exact advanced constructor spelling is intentionally left to the first task's
compile-time contract tests. The user-visible invariants above are not optional.

## Cassette model

One middleware instance opens one cassette session. A cassette contains a versioned,
ordered list of interactions. Each interaction has:

- a sequence number reserved when request handling begins;
- a request fingerprint and sanitized diagnostic request metadata;
- response status and sanitized headers;
- a streamed/non-streamed marker;
- an external body or chunk-frame payload.

Replay compares each request to the next recorded interaction and advances the cursor
only after a successful match. Repeated identical requests therefore receive their
recorded responses in order. A request mismatch, exhausted cassette, missing cassette,
and corrupt cassette are distinct typed failures.

Strict recorded order is the default because it catches scenario drift instead of
searching for any convenient response. The public-contract task must decide whether
unordered matching is needed now; if not, it is deferred rather than exposed as a
premature option.

## Persistence model

Use a versioned cassette directory rather than one JSON document containing every
body and chunk:

```text
recordings/
  cassette.json
  interactions/
    000001/
      interaction.json
      response.body
    000002/
      interaction.json
      response.chunks.ndjson
```

- JSON stores UTF-8 metadata only and is decoded into typed readonly values with
  complete schema validation.
- Synchronous bodies are stored as binary bytes.
- Stream chunks are stored as lazy binary-safe frames (for example, one base64 frame
  per NDJSON line). Replay reads one frame at a time.
- An interaction is written into a uniquely named temporary directory and atomically
  renamed only after successful completion. Session/index updates use locking and an
  atomic replace.
- Partial, failed, or abandoned streams leave no complete interaction and clean up
  temporary files.
- Existing single-file fixtures need an explicit compatibility reader or migration
  command; they must never be silently reinterpreted as the new schema.

## Matching and sensitive data

Matching and persistence are separate projections:

1. The matcher receives the live request and produces a typed fingerprint.
2. The sanitizer produces the diagnostic request/response metadata and replayable
   payload that may be persisted.
3. The store never derives identity from the redacted display representation.

The default matcher should use SHA-256 over a length-delimited canonical projection:
method, normalized URL, streaming mode, canonical JSON body when applicable, raw body
otherwise, and a small allowlist of response-shaping headers such as `content-type`
and `accept`. Credential names come from one shared policy so matcher normalization
and sanitization cannot diverge.

Body sanitization must be boundary-aware. It may use a bounded streaming transformer
or disk-backed finalization, but it must not redact each incoming chunk in isolation
and must not require a `list<string>` of the whole response. Documentation must state
plainly that application payloads can contain private user data and that committed
cassettes require review even after automatic credential sanitization.

## Task breakdown

1. Freeze the immutable public API and compatibility contract.
2. Introduce typed, versioned cassette values and an atomic filesystem store.
3. Implement ordered session recording/replay and typed mismatch/corruption failures.
4. Replace the matcher with a canonical, injectable request fingerprint policy.
5. Make fixture sanitization and record/replay events safe across stream boundaries.
6. Make streamed persistence and replay binary-safe and bounded-memory.
7. Migrate examples/docs, provide legacy-fixture handling, and run full QA plus
   long-stream and hermeticity acceptance tests.

## Dependencies

The public contract is the first gate. The cassette store, matching, and sanitization
can then proceed with narrow overlap. Ordered replay requires the cassette store.
Bounded-memory streaming requires the store and sanitizer contracts. Migration and
release verification require every implementation task.

## Risks and decisions to resolve

- **Legacy fixtures:** compatibility reader versus one-time migration command. The
  chosen path must be explicit and tested.
- **Concurrent requests:** sequence reservation and finalization must remain correct
  when responses complete out of order; unsupported concurrency must fail clearly.
- **Sanitization versus fidelity:** replacing response bytes can affect protocol
  payloads and chunk boundaries. The contract must define this rather than silently
  changing bytes.
- **Strict order:** best for deterministic scenarios, but potentially restrictive for
  pooled clients. Do not add unordered replay without a concrete consumer and tests.
- **API compatibility:** preserve current behavior through deprecations where feasible,
  but do not retain unsafe defaults such as implicit network fallback.

## Completion proof

The epic is complete only when tests prove all of the following through the documented
public API:

- two identical requests record and replay two different responses in order;
- replay misses and corrupt fixtures never contact the network by default;
- malformed metadata and binary/invalid-UTF-8 bodies fail or replay according to the
  typed schema, never as an empty HTTP 200;
- matcher and sanitizer replacements are injected without constructing internal
  repositories;
- a 100K-chunk response records and replays with bounded PHP memory and preserved
  chunk order;
- a secret split across chunk boundaries is not leaked or truncated;
- concurrent writers do not leave torn or partially valid interactions;
- events and exceptions expose only sanitized request summaries;
- examples boot/hub record and replay paths use the new API;
- focused tests, the full `packages/http-client/tests` suite, static analysis, docs QA,
  and the repository QA commands from `CONTRIBUTING.md` pass.
