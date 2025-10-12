<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\PartialGen;

use Cognesy\Utils\Result\Result;

final class PartialObject
{
    public function __construct(
        private PartialHash $hash,
        private mixed $emittable,
        private Result $result,
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty(): self {
        return new self(PartialHash::empty(), null, Result::success(null));
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function hash(): PartialHash {
        return $this->hash;
    }

    public function emittable(): mixed {
        return $this->emittable;
    }

    public function result(): Result {
        return $this->result;
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        PartialHash $hash,
        mixed $emittable,
        Result $result,
    ): self {
        return new self($hash, $emittable, $result);
    }

    public function withEmittable(mixed $value) : self {
        return $this->with($this->hash, $value, $this->result);
    }
}
