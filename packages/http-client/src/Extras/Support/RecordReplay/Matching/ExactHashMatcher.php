<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Matching;

use Cognesy\Http\Data\HttpRequest;

/**
 * Default matcher: two requests are the same iff their method, URL and raw
 * request body are byte-identical. Headers and options are ignored (an
 * Authorization token or timeout must not change which record is served).
 *
 * This is the exact-match baseline extracted from RequestRecords; robustness
 * (canonical-JSON body normalization, volatile-field ignore lists) arrives as
 * alternative RequestMatcher implementations without any caller change.
 */
final class ExactHashMatcher implements RequestMatcher
{
    #[\Override]
    public function fingerprint(HttpRequest $request): string {
        return md5(implode('|', [
            $request->method(),
            $request->url(),
            $request->body()->toString(),
        ]));
    }
}
