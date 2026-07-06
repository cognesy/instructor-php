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
    /** Auth query-param names whose *value* must not affect matching (see below). */
    private const VOLATILE_QUERY_PARAMS = ['key', 'api_key', 'apikey', 'access_token', 'token'];

    #[\Override]
    public function fingerprint(HttpRequest $request): string {
        return md5(implode('|', [
            $request->method(),
            self::normalizeUrl($request->url()),
            $request->body()->toString(),
        ]));
    }

    /**
     * Drop the *values* of credential query params before hashing. Some providers
     * (e.g. Gemini) carry the API key as `?key=…`; without this, a recording made
     * with a real key would never match a keyless replay (dummy key → different
     * URL → different fingerprint). Non-matching URLs are unaffected.
     */
    private static function normalizeUrl(string $url): string {
        $names = implode('|', array_map('preg_quote', self::VOLATILE_QUERY_PARAMS));
        return preg_replace('/([?&](?:' . $names . ')=)[^&#]*/i', '$1', $url) ?? $url;
    }
}
