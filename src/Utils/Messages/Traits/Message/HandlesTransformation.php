<?php
namespace Cognesy\Instructor\Utils\Messages\Traits\Message;

use Cognesy\Instructor\Utils\Messages\Message;
use RuntimeException;

trait HandlesTransformation
{
    public function toArray() : array {
        return ['role' => $this->role, 'content' => $this->content];
    }

    public function toString() : string {
        if (!$this->isComposite()) {
            return $this->content;
        }
        // flatten composite message to text
        $text = '';
        foreach($this->content as $part) {
            if ($part['type'] !== 'text') {
                throw new RuntimeException('Message contains non-text parts and cannot be flattened to text');
            }
            $text .= $part['text'];
        }
        return $text;
    }

    public function toRoleString() : string {
        return $this->role . ': ' . $this->toString();
    }

    public function toCompositeMessage() : Message {
        return Message::fromArray($this->toCompositeArray());
    }

    public function toCompositeArray() : array {
        return match($this->isComposite()) {
            true => [
                'role' => $this->role,
                'content' => $this->content,
            ],
            default => [
                'role' => $this->role,
                'content' => [[
                    'type' => 'text',
                    'text' => $this->content,
                ]]
            ]
        };
    }
}
