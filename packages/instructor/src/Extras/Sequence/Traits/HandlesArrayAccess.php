<?php

namespace Cognesy\Instructor\Extras\Sequence\Traits;

trait HandlesArrayAccess
{
    public function offsetExists(mixed $offset): bool {
        return isset($this->list[$offset]);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->list[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        if (is_null($offset)) {
            $this->list[] = $value;
        } else {
            $this->list[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->list[$offset]);
    }
}