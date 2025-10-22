<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Streaming\PartialGen;

use Cognesy\Utils\Json\Json;

final class PartialHash
{
    public function __construct(
        private string $hash
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty(): self {
        return new self('');
    }

    public static function for(mixed $payload): self {
        $encoded = Json::encode($payload);
        $hash = hash('xxh3', $encoded);
        return new self($hash);
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function equals(self $other): bool {
        return $this->hash === $other->hash;
    }

    public function shouldEmitFor(mixed $payload): bool {
        return !$this->equals(self::for($payload));
    }

    // MUTATORS ////////////////////////////////////////////////

    public function updatedWith(mixed $payload): self {
        return self::for($payload);
    }
}

