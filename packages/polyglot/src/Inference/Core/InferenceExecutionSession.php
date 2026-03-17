<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\Exceptions\ProviderException;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

final class InferenceExecutionSession
{
    private ?DateTimeImmutable $startedAt = null;

    private ?DateTimeImmutable $attemptStartedAt = null;

    private int $attemptNumber = 0;

    private ?InferenceResponse $cachedResponse = null;

    private ?InferenceStream $cachedStream = null;

    private ?\Throwable $terminalError = null;

    public function __construct(
        private InferenceExecution $execution,
        private readonly CanProcessInferenceRequest $driver,
        private readonly EventDispatcherInterface $events,
    ) {}

    public function isStreamed(): bool
    {
        return $this->execution->request()->isStreamed();
    }

    public function executionId(): string
    {
        return $this->execution->id->toString();
    }

    public function stream(): InferenceStream
    {
        if (! $this->isStreamed()) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }

        if ($this->cachedStream !== null) {
            return $this->cachedStream;
        }

        $this->ensureLifecycleStartedForCurrentAttempt();

        $this->cachedStream = new InferenceStream(
            execution: $this->execution,
            driver: $this->driver,
            eventDispatcher: $this->events,
            decorateFinalResponse: null,
        );

        return $this->cachedStream;
    }

    public function response(): InferenceResponse
    {
        if ($this->terminalError !== null) {
            throw $this->terminalError;
        }

        $existingResponse = $this->execution->response();
        if ($existingResponse !== null) {
            return $existingResponse;
        }

        if ($this->shouldCache() && $this->cachedResponse !== null) {
            return $this->cachedResponse;
        }

        if ($this->cachedStream !== null) {
            return $this->responseFromExistingStream();
        }

        return $this->executeResponseLifecycle();
    }

    private function executeResponseLifecycle(): InferenceResponse
    {
        $policy = $this->execution->request()->retryPolicy() ?? new InferenceRetryPolicy;
        $maxAttempts = max(1, $policy->maxAttempts);
        $lengthRetries = 0;

        $this->dispatchInferenceStarted();

        while (true) {
            $this->cachedStream = null;
            $this->dispatchAttemptStarted();

            try {
                $response = $this->makeResponse($this->execution->request());
            } catch (\Throwable $e) {
                $shouldRetry = $this->attemptNumber < $maxAttempts
                    && $policy->shouldRetryException($e);
                $this->execution = $this->execution->withFailedAttempt(null, $this->livePartialUsage(), $e);
                $this->handleAttemptFailure($e, null, $shouldRetry);
                if (! $shouldRetry) {
                    $this->dispatchInferenceCompleted(isSuccess: false);
                    $this->terminalError = $e;
                    throw $e;
                }

                $this->delayForRetry($policy);

                continue;
            }

            $this->execution = match (true) {
                $response->hasFinishedWithFailure() => $this->execution->withFailedAttempt(
                    response: $response,
                    usage: $response->usage(),
                ),
                default => $this->execution->withSuccessfulAttempt(response: $response),
            };

            if ($response->hasFinishedWithFailure()) {
                $this->handleFailedResponse($response, $policy, $lengthRetries);
                $lengthRetries++;

                continue;
            }

            $this->handleAttemptSuccess($response);
            $this->dispatchInferenceCompleted(isSuccess: true);

            if ($this->shouldCache()) {
                $this->cachedResponse = $response;
            }

            return $response;
        }
    }

    private function responseFromExistingStream(): InferenceResponse
    {
        try {
            $response = $this->cachedStream?->final()
                ?? throw new \RuntimeException('Failed to generate final response from stream');
        } catch (\Throwable $e) {
            $partialUsage = $this->livePartialUsage();
            $this->execution = $this->execution->withFailedAttempt(null, $partialUsage, $e);
            $this->handleAttemptFailure($e, null, false);
            $this->dispatchInferenceCompleted(isSuccess: false);
            $this->terminalError = $e;
            throw $e;
        }

        if ($response->hasFinishedWithFailure()) {
            $this->execution = $this->execution->withFailedAttempt(
                response: $response,
                usage: $response->usage(),
            );
            $this->failTerminalResponse($response);
        }

        $this->execution = $this->execution->withSuccessfulAttempt(response: $response);
        $this->handleAttemptSuccess($response);
        $this->dispatchInferenceCompleted(isSuccess: true);

        if ($this->shouldCache()) {
            $this->cachedResponse = $response;
        }

        return $response;
    }

    private function handleFailedResponse(
        InferenceResponse $response,
        InferenceRetryPolicy $policy,
        int $lengthRetries,
    ): void {
        $finishReason = $response->finishReason();

        if ($finishReason === InferenceFinishReason::Length && $policy->shouldRecoverFromLength($lengthRetries)) {
            $error = new \RuntimeException('Inference execution hit length limit; retrying recovery');
            $this->handleAttemptFailure($error, $response, true);
            $this->execution = $this->execution->withRequest(
                $this->buildLengthRecoveryRequest($this->execution->request(), $response, $policy)
            );

            return;
        }

        if ($finishReason === InferenceFinishReason::ContentFilter) {
            $error = new \RuntimeException('Inference blocked by content filter');
            $this->handleAttemptFailure($error, $response, false);
            $this->dispatchInferenceCompleted(isSuccess: false);
            $this->terminalError = $error;
            throw $error;
        }

        $this->failTerminalResponse($response);
    }

    private function failTerminalResponse(InferenceResponse $response): never
    {
        $finishReason = $response->finishReason();
        $error = new \RuntimeException('Inference execution failed: '.$finishReason->value);
        $this->handleAttemptFailure($error, $response, false);
        $this->dispatchInferenceCompleted(isSuccess: false);
        $this->terminalError = $error;
        throw $error;
    }

    private function delayForRetry(InferenceRetryPolicy $policy): void
    {
        $delayMs = $policy->delayMsForAttempt($this->attemptNumber);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    private function cachePolicy(): ResponseCachePolicy
    {
        return $this->execution->request()->responseCachePolicy();
    }

    private function shouldCache(): bool
    {
        return $this->cachePolicy()->shouldCache();
    }

    private function makeResponse(InferenceRequest $request): InferenceResponse
    {
        return match ($this->isStreamed()) {
            false => $this->driver->makeResponseFor($request),
            true => $this->stream()->final() ?? throw new \RuntimeException('Failed to generate final response from stream'),
        };
    }

    private function ensureLifecycleStartedForCurrentAttempt(): void
    {
        $this->dispatchInferenceStarted();

        $currentAttempt = $this->execution->currentAttempt();
        if ($currentAttempt === null || $currentAttempt->isFinalized()) {
            $this->dispatchAttemptStarted();
        }
    }

    private function dispatchInferenceStarted(): void
    {
        if ($this->startedAt !== null) {
            return;
        }

        $this->startedAt = new DateTimeImmutable;
        $this->events->dispatch(new InferenceStarted([
            'executionId' => $this->execution->id->toString(),
            'requestId' => $this->execution->request()->id()->toString(),
            'isStreamed' => $this->isStreamed(),
            'model' => $this->execution->request()->model(),
            'messageCount' => count($this->execution->request()->messages()),
        ]));
    }

    private function dispatchAttemptStarted(): void
    {
        $this->attemptNumber++;
        $this->attemptStartedAt = new DateTimeImmutable;
        $this->execution = $this->execution->startAttempt();

        $this->events->dispatch(new InferenceAttemptStarted(
            executionId: $this->execution->id->toString(),
            attemptId: $this->currentAttemptId(),
            attemptNumber: $this->attemptNumber,
            model: $this->execution->request()->model(),
        ));
    }

    private function handleAttemptSuccess(InferenceResponse $response): void
    {
        $usage = $response->usage();

        $this->events->dispatch(new InferenceAttemptSucceeded([
            'executionId' => $this->execution->id->toString(),
            'attemptId' => $this->currentAttemptId(),
            'attemptNumber' => $this->attemptNumber,
            'finishReason' => $response->finishReason()->value,
            'durationMs' => $this->durationMsSince($this->attemptStartedAt),
            'inputTokens' => $usage->inputTokens,
            'outputTokens' => $usage->outputTokens,
            'cacheWriteTokens' => $usage->cacheWriteTokens,
            'cacheReadTokens' => $usage->cacheReadTokens,
            'reasoningTokens' => $usage->reasoningTokens,
            'totalTokens' => $usage->total(),
        ]));

        $this->events->dispatch(new InferenceUsageReported([
            'executionId' => $this->execution->id->toString(),
            'model' => $this->execution->request()->model(),
            'isFinal' => true,
            'inputTokens' => $usage->inputTokens,
            'outputTokens' => $usage->outputTokens,
            'cacheWriteTokens' => $usage->cacheWriteTokens,
            'cacheReadTokens' => $usage->cacheReadTokens,
            'reasoningTokens' => $usage->reasoningTokens,
            'totalTokens' => $usage->total(),
        ]));
    }

    private function handleAttemptFailure(
        \Throwable $error,
        ?InferenceResponse $response = null,
        bool $willRetry = false,
    ): void {
        $partialUsage = $response?->usage() ?? $this->livePartialUsage();
        $statusCode = match (true) {
            $error instanceof HttpRequestException => $error->getStatusCode(),
            $error instanceof ProviderException => $error->statusCode,
            default => null,
        };

        $this->events->dispatch(new InferenceAttemptFailed($this->attemptFailureEventData(
            executionId: $this->execution->id->toString(),
            attemptId: $this->currentAttemptId(),
            attemptNumber: $this->attemptNumber,
            error: $error,
            willRetry: $willRetry,
            httpStatusCode: $statusCode,
            partialUsage: $partialUsage,
        )));
    }

    private function livePartialUsage(): InferenceUsage
    {
        return $this->cachedStream?->usage() ?? InferenceUsage::none();
    }

    private function buildLengthRecoveryRequest(
        InferenceRequest $request,
        InferenceResponse $response,
        InferenceRetryPolicy $policy,
    ): InferenceRequest {
        $builder = (new InferenceRequestBuilder)->withRequest($request);

        if ($policy->lengthRecovery === 'increase_max_tokens') {
            $current = $request->options()['max_tokens'] ?? null;
            $next = $current !== null
                ? $current + max(1, $policy->maxTokensIncrement)
                : max(1, $policy->maxTokensIncrement);

            return $builder->withMaxTokens($next)->create();
        }

        $messages = $request->messages()
            ->asAssistant($response->content())
            ->asUser($policy->lengthContinuePrompt);

        return $builder->withMessages($messages)->create();
    }

    private function dispatchInferenceCompleted(bool $isSuccess): void
    {
        $response = $this->execution->response();
        $usage = $response?->usage() ?? $this->execution->usage();
        $finishReason = $response?->finishReason() ?? InferenceFinishReason::Error;

        $this->events->dispatch(new InferenceCompleted([
            'executionId' => $this->execution->id->toString(),
            'isSuccess' => $isSuccess,
            'finishReason' => $finishReason->value,
            'durationMs' => $this->durationMsSince($this->startedAt),
            'attemptCount' => $this->attemptNumber,
            'inputTokens' => $usage->inputTokens,
            'outputTokens' => $usage->outputTokens,
            'cacheWriteTokens' => $usage->cacheWriteTokens,
            'cacheReadTokens' => $usage->cacheReadTokens,
            'reasoningTokens' => $usage->reasoningTokens,
            'totalTokens' => $usage->total(),
        ]));
    }

    private function currentAttemptId(): string
    {
        $currentAttempt = $this->execution->currentAttempt();
        if ($currentAttempt === null) {
            throw new \LogicException('Attempt not started before event dispatch.');
        }

        return $currentAttempt->id->toString();
    }

    private function durationMsSince(?DateTimeImmutable $startedAt): float
    {
        if ($startedAt === null) {
            return 0.0;
        }

        $finishedAt = new DateTimeImmutable();
        $interval = $startedAt->diff($finishedAt);
        return ($interval->s * 1000) + ($interval->f * 1000);
    }

    private function attemptFailureEventData(
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        \Throwable $error,
        bool $willRetry,
        ?int $httpStatusCode,
        InferenceUsage $partialUsage,
    ): array {
        return [
            'executionId' => $executionId,
            'attemptId' => $attemptId,
            'attemptNumber' => $attemptNumber,
            'errorMessage' => $this->sanitizeErrorMessage($error->getMessage()),
            'errorType' => get_class($error),
            'httpStatusCode' => $httpStatusCode ?? $this->extractStatusCode($error),
            'willRetry' => $willRetry,
            'durationMs' => $this->durationMsSince($this->attemptStartedAt),
            'partialInputTokens' => $partialUsage->inputTokens,
            'partialOutputTokens' => $partialUsage->outputTokens,
            'partialCacheWriteTokens' => $partialUsage->cacheWriteTokens,
            'partialCacheReadTokens' => $partialUsage->cacheReadTokens,
            'partialReasoningTokens' => $partialUsage->reasoningTokens,
            'partialTotalTokens' => $partialUsage->total(),
        ];
    }

    private function extractStatusCode(\Throwable $error): ?int
    {
        return match (true) {
            $error instanceof HttpRequestException => $error->getStatusCode(),
            $error instanceof ProviderException => $error->statusCode,
            default => null,
        };
    }

    private function sanitizeErrorMessage(string $message): string
    {
        $sanitized = preg_replace_callback(
            '/https?:\/\/[^\s]+/i',
            fn(array $matches): string => $this->redactedUrlWithSuffix($matches[0]),
            $message,
        );

        return $sanitized ?? $message;
    }

    private function redactedUrlWithSuffix(string $url): string
    {
        $suffix = '';
        while ($url !== '' && in_array(substr($url, -1), ['.', ',', ';', ')', ']'], true)) {
            $suffix = substr($url, -1) . $suffix;
            $url = substr($url, 0, -1);
        }

        return $this->redactedUrl($url) . $suffix;
    }

    private function redactedUrl(string $url): string
    {
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

        if (isset($parts['query'])) {
            $parts['query'] = $this->redactedQuery($parts['query']);
        }

        return $this->buildUrl($parts);
    }

    private function redactedQuery(string $query): string
    {
        $segments = explode('&', $query);
        $redacted = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                $redacted[] = $segment;
                continue;
            }

            [$rawKey] = array_pad(explode('=', $segment, 2), 2, null);
            $decodedKey = urldecode((string) $rawKey);
            if (!$this->isSensitiveKey($decodedKey)) {
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
    private function buildUrl(array $parts): string
    {
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

    private function isSensitiveKey(string $key): bool
    {
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
