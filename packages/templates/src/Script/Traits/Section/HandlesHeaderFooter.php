<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

trait HandlesHeaderFooter
{
    public function header() : Messages {
        return $this->header;
    }

    public function footer() : Messages {
        return $this->footer;
    }

    public function withHeader(string|array|Message|Messages $message) : static {
        $this->header->withMessages(Messages::fromAny($message));
        return $this;
    }

    public function withFooter(string|array|Message|Messages $message) : static {
        $this->footer->withMessages(Messages::fromAny($message));
        return $this;
    }
}