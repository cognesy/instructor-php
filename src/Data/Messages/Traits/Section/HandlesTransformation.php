<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Traits\RendersTemplates;
use Cognesy\Instructor\Data\Messages\Utils\ChatFormat;

trait HandlesTransformation
{
    use RendersTemplates;

    public function toMessages() : Messages {
        return $this->messages();
    }

    /**
     * @param array<string,mixed>|null $context
     * @return array<string,mixed>
     */
    public function toArray(array $context = null) : array {
        return $this->renderMessages(
            $this->messages()->toArray(),
            $context
        );
    }

    /**
     * @param ClientType $clientType
     * @param array<string,mixed>|null $context
     * @return array<string,mixed>
     */
    public function toNativeArray(ClientType $clientType, array $context = null) : array {
        $array = $this->renderMessages(
            $this->toArray($context),
            $context,
        );
        return ChatFormat::mapToTargetAPI(
            clientType: $clientType,
            messages: $array,
        );
    }

    /**
     * @param array<string,mixed>|null $context
     * @param string $separator
     * @return array<string,mixed>
     */
    public function toString(array $context = [], string $separator = "\n") : string {
        $text = array_reduce(
            $this->messages()->toArray(),
            fn($carry, $message) => $carry . $message['content'] . $separator,
        );
        return $this->renderString($text, $context);
    }
}
