<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

trait HandlesHeaderFooter
{
    public function header() : Messages {
        return $this->header;
    }

    public function footer() : Messages {
        return $this->footer;
    }

    public function withHeader(string|array|Message|Messages $message) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages,
            header: Messages::fromAny($message),
            footer: $this->footer,
        );
    }

    public function withFooter(string|array|Message|Messages $message) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages,
            header: $this->header,
            footer: Messages::fromAny($message),
        );
    }
}