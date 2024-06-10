<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Message;

trait HandlesTransformation
{
    public function toArray() : array {
        return ['role' => $this->role, 'content' => $this->content];
    }

    public function toString() : string {
        return $this->content;
    }
}