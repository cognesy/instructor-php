<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Retry;

use InvalidArgumentException;
use Throwable;

/**
 * Shared construction-time invariants for inference and embeddings retry
 * policies. Validation happens once, at the policy boundary, so invalid values
 * fail fast with a domain-specific message instead of surfacing later as a
 * ValueError from random_int() or usleep().
 */
final class RetryPolicyInvariants
{
    public static function assertMaxAttempts(int $maxAttempts): void {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException(
                "Invalid retry maxAttempts '{$maxAttempts}'. Must be >= 1.",
            );
        }
    }

    public static function assertDelays(int $baseDelayMs, int $maxDelayMs): void {
        if ($baseDelayMs < 0) {
            throw new InvalidArgumentException(
                "Invalid retry baseDelayMs '{$baseDelayMs}'. Must be >= 0.",
            );
        }
        if ($maxDelayMs < 0) {
            throw new InvalidArgumentException(
                "Invalid retry maxDelayMs '{$maxDelayMs}'. Must be >= 0.",
            );
        }
    }

    /**
     * @param array<array-key,mixed> $retryOnStatus
     */
    public static function assertStatusList(array $retryOnStatus): void {
        foreach ($retryOnStatus as $status) {
            if (!is_int($status) || $status < 100 || $status > 599) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid retryOnStatus entry: expected int HTTP status code from 100 to 599, got %s.',
                    is_int($status) ? (string) $status : get_debug_type($status),
                ));
            }
        }
    }

    /**
     * @param array<array-key,mixed> $retryOnExceptions
     */
    public static function assertExceptionList(array $retryOnExceptions): void {
        foreach ($retryOnExceptions as $exceptionClass) {
            if (!is_string($exceptionClass) || !is_a($exceptionClass, Throwable::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid retryOnExceptions entry: expected class-string of Throwable, got %s.',
                    is_string($exceptionClass) ? "'{$exceptionClass}'" : get_debug_type($exceptionClass),
                ));
            }
        }
    }

    public static function assertNonNegative(int $value, string $field): void {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "Invalid retry {$field} '{$value}'. Must be >= 0.",
            );
        }
    }
}
