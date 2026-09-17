<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Retry;

/**
 * Single owner of the exponential backoff + jitter calculation shared by the
 * inference and embeddings retry policies. Delay values are validated at the
 * policy boundary, so this helper can assume non-negative inputs.
 */
final class RetryBackoff
{
    /**
     * Computes the delay (ms) before the given 1-based attempt number using
     * exponential backoff capped at maxDelayMs, then applies the jitter mode.
     */
    public static function delayMs(
        int $attemptNumber,
        int $baseDelayMs,
        int $maxDelayMs,
        RetryJitter $jitter,
    ): int {
        $attempt = max(1, $attemptNumber);
        if ($baseDelayMs <= 0 || $maxDelayMs <= 0) {
            return 0;
        }
        $capped = min($baseDelayMs, $maxDelayMs);
        for ($number = 1; $number < $attempt && $capped < $maxDelayMs; $number++) {
            if ($capped > intdiv($maxDelayMs, 2)) {
                $capped = $maxDelayMs;
                break;
            }
            $capped *= 2;
        }
        $half = intdiv($capped, 2);

        return match ($jitter) {
            RetryJitter::None => $capped,
            RetryJitter::Equal => $half + random_int(0, $half),
            RetryJitter::Full => random_int(0, $capped),
        };
    }
}
