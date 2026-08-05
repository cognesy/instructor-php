<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Retry;

use InvalidArgumentException;

/**
 * Closed set of retry backoff jitter strategies shared by inference and
 * embeddings retry policies. String-backed so serialized configuration keeps
 * its existing `none|full|equal` values.
 */
enum RetryJitter: string
{
    case None = 'none';
    case Full = 'full';
    case Equal = 'equal';

    /**
     * Resolves a configured string to a jitter mode, rejecting unknown values
     * with a clear domain error instead of silently defaulting.
     */
    public static function fromString(string $value): self {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException(sprintf(
                "Invalid retry jitter mode '%s'. Expected one of: %s.",
                $value,
                implode(', ', array_map(static fn(self $case): string => $case->value, self::cases())),
            ));
    }
}
