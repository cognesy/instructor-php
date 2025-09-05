<?php declare(strict_types=1);

namespace Cognesy\Messages\Traits\Message;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;

trait HandlesMutation
{
    public function withContent(Content $content) : Message {
        return new Message(
            role: $this->role,
            content: $content,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    public function withName(string $name) : Message {
        return new Message(
            role: $this->role,
            content: $this->content,
            name: $name,
            metadata: $this->metadata,
        );
    }

    public function withRole(string|MessageRole $role) : Message {
        $role = match (true) {
            is_string($role) => $role,
            $role instanceof MessageRole => $role->value,
        };
        return new Message(
            role: $role,
            content: $this->content,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    public function addContentFrom(Message $source) : Message {
        $newContent = $this->content->clone();
        foreach ($source->content()->parts() as $part) {
            $newContent = $newContent->addContentPart($part);
        }
        return $this->withContent($newContent);
    }

    public function addContentPart(string|array|ContentPart $part) : Message {
        $newContent = $this->content->addContentPart(ContentPart::fromAny($part));
        return $this->withContent($newContent);
    }
}
