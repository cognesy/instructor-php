<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Domain;

/**
 * Tracks object deduplication state for partial streaming.
 *
 * Ensures we only emit objects when they actually change,
 * using content hashing for comparison.
 *
 * Design: Only tracks hash - result handling is done elsewhere via Result monad in PartialFrame.
 */
final readonly class DeduplicationState
{
    public function __construct(
        public ContentHash $lastHash,
    ) {}

    public static function empty(): self {
        return new self(
            lastHash: ContentHash::empty(),
        );
    }

    /**
     * Check if an object should be emitted (hash changed).
     */
    public function shouldEmit(mixed $object): bool {
        return $this->lastHash->changedFor($object);
    }

    /**
     * Record that we've processed this object.
     */
    public function withHash(ContentHash $hash): self {
        return new self(lastHash: $hash);
    }
}
