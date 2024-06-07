<?php
namespace Cognesy\Instructor\Core\Messages\Traits\Script;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Core\Messages\Messages;
use Cognesy\Instructor\Core\Messages\Utils\ChatFormat;
use Cognesy\Instructor\Utils\Arrays;

trait HandlesTransformation
{
    /**
     * @param array<string> $order
     * @return Messages
     */
    public function toMessages() : Messages {
        $messages = new Messages();
        foreach ($this->sections as $section) {
            $messages->appendMessages($section->toMessages());
        }
        return $messages;
    }

    /**
     * @param array<string> $order
     * @param array<string,mixed>|null $context
     * @return array<string,mixed>
     */
    public function toArray(array $context = null, bool $raw = false) : array {
        $array = $this->toMessages()->toArray();
        return match($raw) {
            false => $this->renderMessages($array, Arrays::mergeNull($this->context, $context)),
            true => $array,
        };
    }

    /**
     * @param ClientType $type
     * @param array<string> $order
     * @param array<string,mixed>|null $context
     * @return array<string,mixed>
     */
    public function toNativeArray(ClientType $type, array $context = null) : array {
        $array = $this->renderMessages(
            $this->toArray(raw: true),
            Arrays::mergeNull($this->context, $context)
        );
        return ChatFormat::mapToTargetAPI(
            clientType: $type,
            messages: $array,
        );
    }

    /**
     * @param array<string> $order
     * @param string $separator
     * @param array<string,mixed>|null $context
     * @return string
     */
    public function toString(string $separator = "\n", array $context = null) : string {
        $text = array_reduce(
            $this->toArray(raw: true),
            fn($carry, $message) => $carry . $message['content'] . $separator,
        );
        if (empty($text)) {
            return '';
        }
        return $this->renderString($text, Arrays::mergeNull($this->context, $context));
    }
}