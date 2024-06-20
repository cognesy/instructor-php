<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Section;
use Cognesy\Instructor\Data\Messages\Traits\RendersTemplates;
use Cognesy\Instructor\Data\Messages\Utils\ChatFormat;
use RuntimeException;

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
            messages: $this->messages()->toArray(),
            context: $context
        );
    }

    public function toMergedPerRole() : static {
        return (new Section($this->name(), $this->description()))
            ->appendMessages($this->messages()->toMergedPerRole());
    }

    /**
     * @param ClientType $clientType
     * @param array<string,mixed>|null $context
     * @return array<string,mixed>
     */
    public function toNativeArray(ClientType $clientType, array $context = null) : array {
        $array = $this->renderMessages(
            messages: $this->toArray($context),
            context: $context,
        );
        return ChatFormat::mapToTargetAPI(
            clientType: $clientType,
            messages: $array,
        );
    }

    /**
     * @param array<string,mixed>|null $context
     * @param string $separator
     * @return array<string, mixed>
     */
    public function toString(array $context = [], string $separator = "\n") : string {
        if ($this->hasComposites()) {
            throw new RuntimeException('Section contains composite messages and cannot be converted to string.');
        }
        $text = array_reduce(
            array: $this->messages()->toArray(),
            callback: fn($carry, $message) => $carry . $message['content'] . $separator,
        );
        return $this->renderString($text, $context);
    }

    public function toRoleString(string $role, string $separator = "\n") : string {
        if ($this->hasComposites()) {
            throw new RuntimeException('Section contains composite messages and cannot be converted to string.');
        }
        $result = '';
        foreach ($this->messages as $message) {
            if ($message->isEmpty() || $message->role() !== $role) {
                continue;
            }
            $result .= $message->toRoleString() . $separator;
        }
        return $result;
    }
}
