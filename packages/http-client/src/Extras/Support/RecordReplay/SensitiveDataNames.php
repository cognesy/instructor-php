<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

/** One credential-name policy shared by matching and fixture sanitization. */
final class SensitiveDataNames
{
    /** @var list<string> */
    public const QUERY_PARAMETERS = [
        'key', 'api_key', 'apikey', 'api-key', 'x-api-key', 'x-goog-api-key',
        'access_token', 'access-token', 'token',
    ];

    /** @var list<string> */
    public const HEADERS = [
        'authorization', 'proxy-authorization', 'x-api-key', 'x-goog-api-key',
        'api-key', 'apikey', 'api_token', 'x-api-token', 'x-auth-token',
        'x-access-token', 'access-token', 'cookie', 'set-cookie',
    ];

    /** @var list<string> */
    public const BODY_FIELDS = [
        'authorization', 'proxy-authorization', 'api-key', 'api_key', 'apikey',
        'x-api-key', 'x-goog-api-key', 'access-token', 'access_token',
        'refresh-token', 'refresh_token', 'id-token', 'id_token', 'token',
        'secret', 'password', 'credential', 'cookie',
    ];

    /** @return list<string> */
    public static function queryParameters(): array
    {
        return self::QUERY_PARAMETERS;
    }

    /** @return list<string> */
    public static function headers(): array
    {
        return self::HEADERS;
    }

    /** @return list<string> */
    public static function bodyFields(): array
    {
        return self::BODY_FIELDS;
    }
}
