<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Matching;

use Cognesy\Http\Data\HttpRequest;

/** Isolated reader for pre-cassette one-file recordings. */
final class LegacyExactHashMatcher implements RequestMatcher
{
    private const VOLATILE_QUERY_PARAMS = ['key', 'api_key', 'apikey', 'access_token', 'token'];

    #[\Override]
    public function fingerprint(HttpRequest $request): string
    {
        return md5(implode('|', [
            $request->method(),
            self::normalizeUrl($request->url()),
            $request->body()->toString(),
        ]));
    }

    private static function normalizeUrl(string $url): string
    {
        $names = implode('|', array_map('preg_quote', self::VOLATILE_QUERY_PARAMS));

        return preg_replace('/([?&](?:' . $names . ')=)[^&#]*/i', '$1', $url) ?? $url;
    }
}
