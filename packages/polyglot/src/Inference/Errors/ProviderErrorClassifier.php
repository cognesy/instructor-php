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
        $payload = self::decodeJson($response->body());
        [$type, $message] = self::extractErrorDetails($payload);

        return self::classify(
            status: $status,
            type: $type,
            message: $message,
            payload: $payload
        );
    }

    public static function fromHttpException(HttpRequestException $error): ProviderException {
        $status = $error->getStatusCode();
        $message = $error->getMessage();
        $type = null;

        return self::classify(
            status: $status,
            type: $type,
            message: $message,
            payload: null
        );
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
        return is_array($decoded) ? $decoded : null;
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
            return [$type ? (string) $type : null, (string) $message];
        }

        $type = $payload['type'] ?? null;
        $message = $payload['message'] ?? 'Provider error';

        return [$type ? (string) $type : null, (string) $message];
    }
}
