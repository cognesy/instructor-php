<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\Section;

use Cognesy\Messages\Messages;

trait HandlesConversion
{
    public function toMessages() : Messages {
        return $this->messages();
    }

    /**
     * Pure conversion to array; no templating or parameters supported.
     *
     * @return array<string,mixed>
     */
    public function toArray() : array {
        return $this->messages()->toArray();
    }

    /**
     * @param string $separator
     * @return array<string, mixed>
     */
    public function toString(string $separator = "\n") : string {
        return implode($separator, [
            $this->header->toString(),
            $this->messages->toString(),
            $this->footer->toString(),
        ]);
    }
}
