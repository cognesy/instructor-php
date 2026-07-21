<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Config;

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
        $base = $baseDelayMs * (2 ** ($attempt - 1));
        $capped = (int) min($base, $maxDelayMs);

        if ($capped <= 0) {
            return 0;
        }

        return match ($jitter) {
            RetryJitter::None => $capped,
            RetryJitter::Equal => (int) ($capped / 2 + random_int(0, intdiv($capped, 2))),
            RetryJitter::Full => random_int(0, $capped),
        };
    }
}
