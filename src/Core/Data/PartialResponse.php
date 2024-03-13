<?php

namespace Cognesy\Instructor\Core\Data;

class PartialResponse
{
    private bool $isPartial;
    private mixed $response;

    public function __construct(mixed $response, bool $isPartial) {
        $this->response = $response;
        $this->isPartial = $isPartial;
    }

    public function partial() : mixed {
        if (!$this->isPartial) {
            return null;
        }
        return $this->response;
    }

    public function complete() : mixed {
        if ($this->isPartial) {
            return null;
        }
        return $this->response;
    }

    public function isPartial() : bool {
        return $this->isPartial;
    }

    public function isComplete() : bool {
        return !$this->isPartial;
    }
}