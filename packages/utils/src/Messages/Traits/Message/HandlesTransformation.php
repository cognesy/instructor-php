<?php
namespace Cognesy\Utils\Messages\Traits\Message;

use Cognesy\Utils\Messages\Message;
use RuntimeException;

trait HandlesTransformation
{
    public function toArray() : array {
        return array_filter([
            'role' => $this->role,
            'name' => $this->name,
            'content' => $this->content,
            '_metadata' => $this->metadata,
        ]);
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
        return new Message(
            role: $this->role,
            content: match(true) {
                $this->isComposite() => $this->content,
                default => [['type' => 'text', 'text' => $this->content]]
            },
            metadata: $this->metadata,
        );
    }
}
