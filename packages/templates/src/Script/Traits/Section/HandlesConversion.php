<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Template\Script\Traits\RendersContent;
use Cognesy\Messages\Messages;
use RuntimeException;

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
     * @param array<string,mixed>|null $parameters
     * @param string $separator
     * @return array<string, mixed>
     */
    public function toString(array $parameters = [], string $separator = "\n") : string {
        if ($this->hasComposites()) {
            // TODO: we should check if composites are text only and allow conversion if so
            throw new RuntimeException('Section contains composite messages and cannot be converted to string.');
        }
        $text = array_reduce(
            array: $this->messages()->toArray(),
            callback: fn($carry, $message) => $carry . $message['content'] . $separator,
        );
        return $this->renderString($text, $parameters);
    }
}