<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Traits\RendersContent;
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
    public function toArray(array $parameters = null) : array {
        return $this->renderMessages(
            messages: $this->messages()->toArray(),
            parameters: $parameters
        );
    }

    /**
     * @param ClientType $clientType
     * @param array<string,mixed>|null $parameters
     * @return array<string,mixed>
     */
    public function toNativeArray(ClientType $clientType, array $parameters = null) : array {
        $array = $this->renderMessages(
            messages: $this->toArray($parameters),
            parameters: $parameters,
        );
        return $clientType->toNativeMessages($array);
    }

    /**
     * @param array<string,mixed>|null $parameters
     * @param string $separator
     * @return array<string, mixed>
     */
    public function toString(array $parameters = [], string $separator = "\n") : string {
        if ($this->hasComposites()) {
            throw new RuntimeException('Section contains composite messages and cannot be converted to string.');
        }
        $text = array_reduce(
            array: $this->messages()->toArray(),
            callback: fn($carry, $message) => $carry . $message['content'] . $separator,
        );
        return $this->renderString($text, $parameters);
    }
}