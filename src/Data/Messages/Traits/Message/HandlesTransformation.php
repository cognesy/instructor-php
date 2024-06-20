<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Message;

use RuntimeException;

trait HandlesTransformation
{
    public function toArray() : array {
        return ['role' => $this->role, 'content' => $this->content];
    }

    public function toString() : string {
        if ($this->isComposite()) {
            throw new RuntimeException('Cannot convert composite message to string');
        }
        return $this->content;
    }

    public function toRoleString() : string {
        if ($this->isComposite()) {
            throw new RuntimeException('Cannot convert composite message to string');
        }
        return $this->role . ': ' . $this->content;
    }
}