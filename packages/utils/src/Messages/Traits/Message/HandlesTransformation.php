<?php
namespace Cognesy\Utils\Messages\Traits\Message;

trait HandlesTransformation
{
    public function toArray() : array {
        return array_filter([
            'role' => $this->role,
            'name' => $this->name,
            'content' => match(true) {
                $this->content->isEmpty() => '',
                $this->content->isComposite() => $this->content->toArray(),
                default => $this->content->toString(),
            },
            '_metadata' => $this->metadata,
        ]);
    }

    public function toString() : string {
        return $this->content->toString();
    }
}
