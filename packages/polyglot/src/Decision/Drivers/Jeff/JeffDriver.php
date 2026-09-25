<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Jeff;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Contracts\DecisionResponseAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class JeffDriver implements CanProcessDecisionRequest
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
        $this->requestAdapter = $requestAdapter ?? new JeffRequestAdapter($config);
        $this->responseAdapter = $responseAdapter ?? new JeffResponseAdapter;
    }

    #[Override]
    public function handle(DecisionRequest $request): DecisionResponse
    {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        try {
            $response = $this->httpClient->send($clientRequest)->get();
        } catch (HttpRequestException $exception) {
            throw self::providerError(
                $exception->getStatusCode(),
                self::header($exception->getResponse()?->headers() ?? [], 'retry-after'),
            );
        }

        if ($response->statusCode() >= 400) {
            throw self::providerError(
                $response->statusCode(),
                self::header($response->headers(), 'retry-after'),
            );
        }

        return $this->responseAdapter->fromHttpResponse($response, $request);
    }

    private static function providerError(?int $statusCode, ?string $retryAfter = null): DecisionProviderException
    {
        return match (true) {
            $statusCode === 401 || $statusCode === 403 => new DecisionAuthenticationException(
                "Jeff authentication failed (HTTP {$statusCode}).",
                $statusCode,
                $retryAfter,
            ),
            $statusCode === 429 => new DecisionRateLimitException(
                'Jeff rate limit exceeded (HTTP 429).',
                $statusCode,
                $retryAfter,
            ),
            $statusCode === null => new DecisionTransientException('Jeff network request failed.'),
            $statusCode === 408 || $statusCode === 529 || $statusCode >= 500 => new DecisionTransientException(
                "Jeff service is temporarily unavailable (HTTP {$statusCode}).",
                $statusCode,
                $retryAfter,
            ),
            default => new DecisionInvalidRequestException(
                "Jeff rejected the request (HTTP {$statusCode}).",
                $statusCode,
                $retryAfter,
            ),
        };
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
