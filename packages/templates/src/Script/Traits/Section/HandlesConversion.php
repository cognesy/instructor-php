<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Messages\Messages;
use Cognesy\Template\Script\Traits\RendersContent;

trait HandlesConversion
{
    use RendersContent;

    public function toMessages() : Messages {
        return $this->messages();
    }

    /**
     * @param array<string,mixed>|null $parameters
     * @return array<string,mixed>
     */
    public function toArray(?array $parameters = null) : array {
        return $this->renderMessages(
            messages: $this->messages(),
            parameters: $parameters
        )->toArray();
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