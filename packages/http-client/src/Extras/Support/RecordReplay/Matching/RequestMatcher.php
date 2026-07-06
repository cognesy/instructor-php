<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Matching;

use Cognesy\Http\Data\HttpRequest;

/**
 * Decides which recorded interaction satisfies a request.
 *
 * The volatile decision hidden behind this contract is *what counts as "the
 * same request"* — exact bytes today (see {@see ExactHashMatcher}), canonical
 * JSON / volatile-field-tolerant matching later. It is expressed as a stable
 * fingerprint: two requests match iff their fingerprints are equal. Storage
 * uses the fingerprint as its lookup key, so swapping the matching strategy
 * never touches callers.
 */
interface RequestMatcher
{
    /**
     * A stable key identifying the equivalence class of a request.
     * Deterministic for a given request; equal fingerprints ⇒ a match.
     */
    public function fingerprint(HttpRequest $request): string;
}
