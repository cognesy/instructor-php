# Verification on 1 October 2026

These results describe this example and its companion article. They are not a
benchmark or a guarantee about future provider responses.

| Check | Result |
| --- | --- |
| `php examples/B08_Retrieval/SupportReplyWithSources/run.php` | Passed all deterministic assertions. |
| `just examples-replay support_reply_with_sources` | Passed through the normal Hub runner. |
| `php examples/B08_Retrieval/SupportReplyWithSources/run.php --live` with recording enabled | Final live run passed source and citation checks; initial live draft was rejected for missing inline labels. |
| `INSTRUCTOR_EXAMPLES_HTTP=replay php examples/B08_Retrieval/SupportReplyWithSources/run.php --live` | Passed offline using the final 9-interaction cassette. |
| `vendor/bin/pest packages/retrieval/tests/Unit` | 67 tests, 335 assertions passed. |
| PHP syntax checks on all 9 example PHP files | Passed, including the walkthrough output formatter. |
| Pint using `packages/agents/pint.json` on this example | Passed. |
| Dedicated PHPStan level 8 over `src/` and `proof.php` | No errors. |
| Dedicated Psalm level 4 over `src/`, `proof.php`, and a reference to the proof entry point | No errors; one informational nullable-operand observation in proof output. Shared bootstrap was verified through actual execution rather than included in this scoped analysis. |
| All 4 PHP article excerpts executed sequentially, with embeddings and inference replaced by the same fixture providers | Passed; produced 2 sources, one generation call, and an accepted citation check. |
| `composer qa:docs -- retrieval` | Passed; package documentation currently contains zero checked snippets. |
| `composer qa:docs-sites` | Both deployable documentation targets valid; generated source navigation adds this example. |
| `composer content:check` in `instructor-www` | Passed, including website PHPStan/Psalm, content and internal links, and 42 focused tests / 170 assertions. |
| Local browser inspection | Article and index entry render; code highlighting, 2 tables, proof and related links verified; no page-width overflow at the inspected viewport. |

## Wider checks with unrelated findings

`just test-slow` in `instructor-www` ran 45 passing tests with 181 assertions,
4 skipped tests, and 2 failures in unchanged tests:

- `ExampleTest.php:9` expects an old report URL in the homepage. Tracked as
  `instructor-www-dh8`.
- `ProfileTest.php:13` expects `/profile` to return 200; that route is retired and
  returns 404. Already tracked as `instructor-www-53j`.

`ripwire . --quality-delta` reported broad findings on unchanged source and
export-ignored tests/recordings. The baseline-comparison investigation is
tracked as `instructor-98di`. The new example's PHP findings concerned static
reachability of constructors, enum, runner and interface methods, plus aggregate
class length. No complexity, nesting, or duplication finding concerned this
example's PHP code.

Beads epic: `instructor-t0sd`. Public article source lives in the sibling
`instructor-www` repository at
`resources/content/articles/draft-support-replies-from-your-knowledge-base-in-php.md`.

## Runtime explanation revision

Task `instructor-gp27` adds narrated runtime stages while preserving the native
PHP template documentation. The live provider workflow now precedes the fixture
checks. The recorded provider cassette replays unchanged: formatting does not
change the embedding or generation requests. A separately saved
`recorded-walkthrough.txt` shows the new presentation of the captured run.
