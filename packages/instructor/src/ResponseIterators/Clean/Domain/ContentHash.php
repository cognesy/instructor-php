<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Domain;

use Cognesy\Utils\Json\Json;

/**
 * Hash of content for deduplication.
 *
 * Uses fast xxh3 hashing to detect when emitted content changes.
 * Prevents emitting duplicate objects when streaming.
 */
final readonly class ContentHash
{
    private function __construct(
        private string $hash,
    ) {}

    public static function empty(): self {
        return new self('');
    }

    public static function of(mixed $content): self {
        $encoded = Json::encode($content);
        $hash = hash('xxh3', $encoded);
        return new self($hash);
    }

    public function equals(self $other): bool {
        return $this->hash === $other->hash;
    }

    public function changedFor(mixed $content): bool {
        return !$this->equals(self::of($content));
    }
}
