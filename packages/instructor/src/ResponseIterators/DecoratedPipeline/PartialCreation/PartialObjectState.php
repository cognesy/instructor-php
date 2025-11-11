<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\DecoratedPipeline\PartialCreation;

use Cognesy\Utils\Result\Result;

final class PartialObjectState
{
    public function __construct(
        private PartialHash $hash,
        private mixed $emittable,
        private Result $result,
    ) {}

    public static function empty(): self {
        return new self(PartialHash::empty(), null, Result::success(null));
    }

    public function hash(): PartialHash {
        return $this->hash;
    }

    public function emittable(): mixed {
        return $this->emittable;
    }

    public function result(): Result {
        return $this->result;
    }

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

