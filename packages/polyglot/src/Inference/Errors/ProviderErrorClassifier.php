<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Errors;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderAuthenticationException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderInvalidRequestException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderQuotaExceededException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderRateLimitException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderTransientException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderException;

final class ProviderErrorClassifier
{
    public static function fromHttpResponse(HttpResponse $response): ProviderException {
        $status = $response->statusCode();
        $payload = self::decodeJson(self::safeBody($response));
        [$type, $message] = self::extractErrorDetails($payload);

        return self::classify(
            status: $status,
            type: $type,
            message: $message,
            payload: $payload
        );
    }

    public static function fromHttpException(HttpRequestException $error): ProviderException {
        $response = $error->getResponse();
        $status = $error->getStatusCode() ?? $response?->statusCode();
        $payload = match (true) {
            $response instanceof HttpResponse => self::decodeJson(self::safeBodyFromResponse($response)),
            default => null,
        };
        [$type, $payloadMessage] = self::extractErrorDetails($payload);

        $message = match (true) {
            $payloadMessage !== '' && $payloadMessage !== 'Provider error' => $payloadMessage,
            default => self::sanitizedHttpExceptionMessage($error),
        };

        return self::classify(
            status: $status,
            type: $type,
            message: $message,
            payload: $payload
        );
    }

    private static function sanitizedHttpExceptionMessage(HttpRequestException $error): string {
        $request = $error->getRequest();
        if ($request === null) {
            return $error->getMessage();
        }

        $url = $request->url();
        return str_replace($url, self::redactedUrl($url), $error->getMessage());
    }

    private static function classify(
        ?int $status,
        ?string $type,
        string $message,
        ?array $payload
    ): ProviderException {
        $normalized = strtolower((string) $type);

        if ($status === 429 || str_contains($normalized, 'rate_limit')) {
            return new ProviderRateLimitException($message, $status, $payload);
        }

        if (str_contains($normalized, 'insufficient_quota') || ($status === 402)) {
            return new ProviderQuotaExceededException($message, $status, $payload);
        }

        if ($status === 401 || $status === 403 || str_contains($normalized, 'auth')) {
            return new ProviderAuthenticationException($message, $status, $payload);
        }

        if ($status === 408) {
            return new ProviderTransientException($message, $status, $payload);
        }

        if (in_array($status, [400, 404, 409, 422], true) || str_contains($normalized, 'invalid')) {
            return new ProviderInvalidRequestException($message, $status, $payload);
        }

        if (($status !== null && $status >= 500) || str_contains($normalized, 'overloaded') || str_contains($normalized, 'server')) {
            return new ProviderTransientException($message, $status, $payload);
        }

        return new ProviderInvalidRequestException($message, $status, $payload);
    }

    private static function decodeJson(string $body): ?array {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return self::decodeJsonFromSse($body);
    }

    private static function safeBody(HttpResponse $response): string {
        if ($response->isStreamed()) {
            return '';
        }
        return $response->body();
    }

    private static function safeBodyFromResponse(HttpResponse $response): string {
        if ($response->isStreamed()) {
            return '';
        }
        return $response->body();
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function extractErrorDetails(?array $payload): array {
        if (!is_array($payload)) {
            return [null, 'Provider error'];
        }

        $error = $payload['error'] ?? $payload['errors'] ?? null;

        if (is_string($error)) {
            return [null, $error];
        }

        if (is_array($error)) {
            $type = $error['type'] ?? $error['code'] ?? $payload['type'] ?? null;
            $message = $error['message'] ?? $error['detail'] ?? $payload['message'] ?? 'Provider error';
            $param = $error['param'] ?? null;
            $code = $error['code'] ?? null;
            $formatted = self::formatMessageWithDetails((string) $message, $param, $code);
            return [$type ? (string) $type : null, $formatted];
        }

        $type = $payload['type'] ?? null;
        $message = $payload['message'] ?? 'Provider error';

        $param = $payload['param'] ?? null;
        $code = $payload['code'] ?? null;
        $formatted = self::formatMessageWithDetails((string) $message, $param, $code);

        return [$type ? (string) $type : null, $formatted];
    }

    private static function decodeJsonFromSse(string $body): ?array {
        if ($body === '') {
            return null;
        }

        $lines = preg_split('/\R/', $body) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, 'data:')) {
                continue;
            }

            $data = trim(substr($trimmed, 5));
            if ($data === '' || $data === '[DONE]') {
                continue;
            }

            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function formatMessageWithDetails(string $message, mixed $param, mixed $code): string {
        $details = [];
        if (is_string($param) && $param !== '') {
            $details[] = "param={$param}";
        }
        if (is_string($code) && $code !== '') {
            $details[] = "code={$code}";
        }
        if ($details === []) {
            return $message;
        }
        $detailsText = implode(', ', $details);
        return "{$message} ({$detailsText})";
    }

    private static function redactedUrl(string $url): string {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        if (isset($parts['user']) && $parts['user'] !== '') {
            $parts['user'] = '[REDACTED]';
        }

        if (isset($parts['pass']) && $parts['pass'] !== '') {
            $parts['pass'] = '[REDACTED]';
        }

        if (!isset($parts['query'])) {
            return self::buildUrl($parts);
        }

        $parts['query'] = self::redactedQuery($parts['query']);
        return self::buildUrl($parts);
    }

    private static function redactedQuery(string $query): string {
        $segments = explode('&', $query);
        $redacted = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                $redacted[] = $segment;
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $segment, 2), 2, null);
            $decodedKey = urldecode((string) $rawKey);
            if (!self::isSensitiveKey($decodedKey)) {
                $redacted[] = $segment;
                continue;
            }

            $redacted[] = $rawKey . '=' . rawurlencode('[REDACTED]');
        }

        return implode('&', $redacted);
    }

    /**
     * @param array<string,mixed> $parts
     */
    private static function buildUrl(array $parts): string {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }

    private static function isSensitiveKey(string $key): bool {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        if (in_array($normalized, ['apikey', 'authorization', 'proxyauthorization', 'token', 'accesstoken', 'refreshtoken', 'secret', 'password', 'cookie', 'setcookie'], true)) {
            return true;
        }

        if (str_contains($normalized, 'apikey')) {
            return true;
        }

        if (str_contains($normalized, 'authorization')) {
            return true;
        }

        if (str_contains($normalized, 'cookie')) {
            return true;
        }

        return str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'password');
    }
}
