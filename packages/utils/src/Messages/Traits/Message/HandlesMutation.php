<?php

namespace Cognesy\Utils\Messages\Traits\Message;

use Cognesy\Utils\Messages\Content;
use Cognesy\Utils\Messages\ContentPart;
use Cognesy\Utils\Messages\Message;

trait HandlesMutation
{
    public function addContentFrom(Message $source) : static {
        foreach ($source->content()->parts() as $part) {
            $this->content->addContentPart($part);
        }
        return $this;
    }

    public function addContentPart(string|array|ContentPart $part) : static {
        $this->content->addContentPart(ContentPart::fromAny($part));
        return $this;
    }

    public function withRole(string $role) : static {
        $this->role = $role;
        return $this;
    }

    public function appendContentFields(array $fields) : static {
        $this->content->appendContentFields($fields);
        return $this;
    }

    public function appendContentField(string $key, mixed $value) : static {
        $this->content->appendContentField($key, $value);
        return $this;
    }

    public function removeContent() : void {
        $this->content = new Content();
    }
}
