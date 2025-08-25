<?php declare(strict_types=1);

namespace Cognesy\Messages\Traits\Message;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;

trait HandlesMutation
{
    public function withContent(Content $content) : static {
        return new static(
            role: $this->role,
            content: $content,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    public function withRole(string|MessageRole $role) : static {
        $role = match (true) {
            is_string($role) => $role,
            $role instanceof MessageRole => $role->value,
        };
        return new static(
            role: $role,
            content: $this->content,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    public function addContentFrom(Message $source) : static {
        $newContent = $this->content->clone();
        foreach ($source->content()->parts() as $part) {
            $newContent = $newContent->addContentPart($part);
        }
        return $this->withContent($newContent);
    }

    public function addContentPart(string|array|ContentPart $part) : static {
        $newContent = $this->content->addContentPart(ContentPart::fromAny($part));
        return $this->withContent($newContent);
    }
}
