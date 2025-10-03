<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Utils\Result\Result;

final class PartialObjectUpdate
{
    public function __construct(
        private PartialObject $state,
        private mixed $emittable,
        private Result $result,
    ) {}

    public function state(): PartialObject {
        return $this->state;
    }

    public function emittable(): mixed {
        return $this->emittable;
    }

    public function result(): Result {
        return $this->result;
    }
}

