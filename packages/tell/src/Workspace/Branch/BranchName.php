<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

use InvalidArgumentException;

/**
 * A closed, portable user branch identifier.
 *
 * Names are 1-64 lowercase ASCII characters: a leading letter followed by
 * letters, digits, or hyphens. Unicode and uppercase are rejected, so storage
 * is portable and case ambiguity cannot occur. `main` and the internal,
 * session, and agent prefixes are reserved for Tell-owned namespaces.
 */
final readonly class BranchName
{
    private const array RESERVED_NAMES = ['main', 'internal', 'session', 'agent'];
    private const array RESERVED_PREFIXES = ['internal-', 'session-', 'agent-'];

    private function __construct(private string $value) {}

    public static function from(string $value): self {
        return self::parse($value, allowChild: false);
    }

    /** Tell-only branch namespace for a persistent delegated child run. */
    public static function child(string $value): self {
        return self::parse($value, allowChild: true);
    }

    /** Accepts existing stored user and Tell-owned child names. */
    public static function fromStored(string $value): self {
        return self::parse($value, allowChild: true);
    }

    private static function parse(string $value, bool $allowChild): self {
        if (preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $value) !== 1) {
            throw new InvalidArgumentException(
                'Tell branch names must be 1-64 lowercase ASCII letters, digits, or hyphens and start with a letter.',
            );
        }
        if (in_array($value, self::RESERVED_NAMES, true) || self::hasReservedPrefix($value, $allowChild)) {
            throw new InvalidArgumentException("Tell branch name '{$value}' is reserved.");
        }

        return new self($value);
    }

    public function toString(): string {
        return $this->value;
    }

    private static function hasReservedPrefix(string $value, bool $allowChild): bool {
        foreach (self::RESERVED_PREFIXES as $prefix) {
            if ($allowChild && $prefix === 'agent-') {
                continue;
            }
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
