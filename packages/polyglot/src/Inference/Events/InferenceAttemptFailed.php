<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderException;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use DateTimeImmutable;
use Throwable;

/**
 * Dispatched when an inference attempt fails.
 * May be followed by a retry (InferenceAttemptStarted with higher attemptNumber).
 */
final class InferenceAttemptFailed extends InferenceEvent
{
    public readonly DateTimeImmutable $failedAt;
    public readonly float $durationMs;

    public function __construct(
        public readonly string $executionId,
        public readonly string $attemptId,
        public readonly int $attemptNumber,
        public readonly string $errorMessage,
        public readonly ?string $errorType = null,
        public readonly ?int $httpStatusCode = null,
        public readonly bool $willRetry = false,
        public readonly ?InferenceUsage $partialUsage = null,
        ?DateTimeImmutable $startedAt = null,
    ) {
        $this->failedAt = new DateTimeImmutable();
        $this->durationMs = $startedAt !== null
            ? $this->calculateDurationMs($startedAt, $this->failedAt)
            : 0.0;

        parent::__construct([
            'executionId' => $this->executionId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'errorMessage' => $this->errorMessage,
            'errorType' => $this->errorType,
            'httpStatusCode' => $this->httpStatusCode,
            'willRetry' => $this->willRetry,
            'durationMs' => $this->durationMs,
            'partialUsage' => $this->partialUsage?->toArray(),
        ]);
    }

    public static function fromThrowable(
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        Throwable $error,
        bool $willRetry = false,
        ?int $httpStatusCode = null,
        ?InferenceUsage $partialUsage = null,
        ?DateTimeImmutable $startedAt = null,
    ): self {
        $resolvedHttpStatusCode = $httpStatusCode ?? self::extractStatusCode($error);

        return new self(
            executionId: $executionId,
            attemptId: $attemptId,
            attemptNumber: $attemptNumber,
            errorMessage: self::sanitizeErrorMessage($error->getMessage()),
            errorType: get_class($error),
            httpStatusCode: $resolvedHttpStatusCode,
            willRetry: $willRetry,
            partialUsage: $partialUsage,
            startedAt: $startedAt,
        );
    }

    private function calculateDurationMs(DateTimeImmutable $start, DateTimeImmutable $end): float {
        $interval = $start->diff($end);
        return ($interval->s * 1000) + ($interval->f * 1000);
    }

    private static function extractStatusCode(Throwable $error): ?int {
        return match (true) {
            $error instanceof HttpRequestException => $error->getStatusCode(),
            $error instanceof ProviderException => $error->statusCode,
            default => null,
        };
    }

    private static function sanitizeErrorMessage(string $message): string {
        $sanitized = preg_replace_callback(
            '/https?:\/\/[^\s]+/i',
            static function (array $matches): string {
                $url = $matches[0];
                $suffix = '';
                while ($url !== '' && in_array(substr($url, -1), ['.', ',', ';', ')', ']'], true)) {
                    $suffix = substr($url, -1) . $suffix;
                    $url = substr($url, 0, -1);
                }

                return self::redactedUrl($url) . $suffix;
            },
            $message,
        );

        return $sanitized ?? $message;
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

        if (isset($parts['query'])) {
            $parts['query'] = self::redactedQuery($parts['query']);
        }

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

    #[\Override]
    public function __toString(): string {
        $retryInfo = $this->willRetry ? ' (will retry)' : ' (final)';
        $statusInfo = $this->httpStatusCode !== null ? " status={$this->httpStatusCode}" : '';
        return sprintf(
            'Attempt #%d failed [%s]%s: %s%s',
            $this->attemptNumber,
            $this->attemptId,
            $statusInfo,
            $this->errorMessage,
            $retryInfo
        );
    }
}
