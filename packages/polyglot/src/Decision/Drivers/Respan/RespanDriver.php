<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Respan;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Contracts\DecisionResponseAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAccessException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use JsonException;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;

final readonly class RespanDriver implements CanProcessDecisionRequest
{
    private DecisionRequestAdapter $requestAdapter;

    private DecisionResponseAdapter $responseAdapter;

    public function __construct(
        private DecisionConfig $config,
        private CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
        ?DecisionRequestAdapter $requestAdapter = null,
        ?DecisionResponseAdapter $responseAdapter = null,
    ) {
        $this->requestAdapter = $requestAdapter ?? new RespanRequestAdapter($config);
        $this->responseAdapter = $responseAdapter ?? new RespanResponseAdapter;
    }

    #[Override]
    public function handle(DecisionRequest $request): DecisionResponse
    {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        try {
            $response = $this->httpClient->send($clientRequest)->get();
        } catch (HttpRequestException $exception) {
            throw self::providerError(
                statusCode: $exception->getStatusCode(),
                response: $exception->getResponse(),
            );
        }

        if ($response->statusCode() >= 400) {
            throw self::providerError($response->statusCode(), $response);
        }

        return $this->responseAdapter->fromHttpResponse($response, $request);
    }

    private static function providerError(?int $statusCode, ?HttpResponse $response = null): DecisionProviderException
    {
        $retryAfter = self::header($response?->headers() ?? [], 'retry-after');
        $detail = self::detail($response);
        $suffix = $detail === null ? '' : ": {$detail}";

        return match (true) {
            $statusCode === 401 => new DecisionAuthenticationException(
                'RESPAN authentication failed (HTTP 401).',
                $statusCode,
                $retryAfter,
            ),
            $statusCode === 402 || ($statusCode === 403 && self::isAccessDetail($detail)) => new DecisionAccessException(
                "RESPAN access denied (HTTP {$statusCode}){$suffix}",
                $statusCode,
                $retryAfter,
            ),
            $statusCode === 403 => new DecisionAuthenticationException(
                'RESPAN authentication or authorization failed (HTTP 403).',
                $statusCode,
                $retryAfter,
            ),
            $statusCode === 429 => new DecisionRateLimitException(
                'RESPAN rate limit exceeded (HTTP 429).',
                $statusCode,
                $retryAfter,
            ),
            $statusCode === null => new DecisionTransientException('RESPAN network request failed.'),
            $statusCode === 408 || $statusCode === 424 || $statusCode >= 500 => new DecisionTransientException(
                "RESPAN service is temporarily unavailable (HTTP {$statusCode}).",
                $statusCode,
                $retryAfter,
            ),
            default => new DecisionInvalidRequestException(
                "RESPAN rejected the request (HTTP {$statusCode}){$suffix}",
                $statusCode,
                $retryAfter,
            ),
        };
    }

    private static function detail(?HttpResponse $response): ?string
    {
        if ($response === null) {
            return null;
        }
        try {
            $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (! $body instanceof stdClass || ! is_string($body->detail ?? null)) {
            return null;
        }
        $detail = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $body->detail) ?? '');

        return $detail === '' ? null : substr($detail, 0, 300);
    }

    private static function isAccessDetail(?string $detail): bool
    {
        if ($detail === null) {
            return false;
        }
        $detail = strtolower($detail);

        return str_contains($detail, 'not enabled')
            || str_contains($detail, 'credit')
            || str_contains($detail, 'access');
    }

    /** @param array<string, string|array<string>> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $header => $value) {
            if (strtolower($header) !== $name) {
                continue;
            }
            $first = is_array($value) ? ($value[0] ?? null) : $value;

            return is_string($first) && trim($first) !== '' ? $first : null;
        }

        return null;
    }
}
