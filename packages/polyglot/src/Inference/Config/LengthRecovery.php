<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Config;

use InvalidArgumentException;

/**
 * Closed set of inference length-limit recovery strategies. String-backed so
 * serialized configuration keeps its existing `none|continue|increase_max_tokens`
 * values. This concern is inference-specific and is intentionally not shared
 * with the common transport retry policy.
 */
enum LengthRecovery: string
{
    case None = 'none';
    case Continue = 'continue';
    case IncreaseMaxTokens = 'increase_max_tokens';

    /**
     * Resolves a configured string to a recovery mode, rejecting unknown values
     * with a clear domain error instead of silently enabling continuation.
     */
    public static function fromString(string $value): self {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException(sprintf(
                "Invalid length recovery mode '%s'. Expected one of: %s.",
                $value,
                implode(', ', array_map(static fn(self $case): string => $case->value, self::cases())),
            ));
    }
}
