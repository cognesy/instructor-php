<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Script;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Utils\ChatFormat;
use Exception;

trait HandlesTransformation
{
    /**
     * @param array<string> $order
     * @return Messages
     */
    public function toMessages(array $context = null) : Messages {
        $messages = new Messages();
        foreach ($this->sections as $section) {
            $content = match(true) {
                $section->isTemplate() => $this->fromTemplate(
                    name: $section->name(),
                    context: $this->context()->merge($context)->toArray(),
                ) ?? $section->toMessages(),
                default => $section->toMessages(),
            };
            if ($content->isEmpty()) {
                continue;
            }
            $messages->appendMessages($content);
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
            false => $this->renderMessages(
                messages: $array,
                context: $this->context()->merge($context)->toArray()),
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
            messages: $this->toArray(raw: true),
            context: $this->context()->merge($context)->toArray(),
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
            array: $this->toArray(raw: true),
            callback: fn($carry, $message) => $carry . $message['content'] . $separator,
            initial: '',
        );
        if (empty($text)) {
            return '';
        }
        return $this->renderString(
            template: $text,
            context: $this->context()->merge($context)->toArray()
        );
    }

    protected function fromTemplate(string $name, ?array $context) : Messages {
        if (empty($context)) {
            return new Messages();
        }

        $source = $context[$name] ?? throw new Exception("Context does not have template value: $name");

        // process value from context
        $values = match(true) {
            is_callable($source) => $source($context),
            is_array($source) => Messages::fromArray($source),
            $source instanceof Messages => $source,
            is_string($source) => Messages::fromString('user', $source),
            default => throw new Exception("Invalid template value: $name"),
        };

        // process results of callable context value
        return match(true) {
            $values instanceof Messages => $values,
            is_array($values) => Messages::fromArray($values),
            is_string($values) => Messages::fromString('user', $values),
            default => throw new Exception("Invalid template value: $name"),
        };
    }
}