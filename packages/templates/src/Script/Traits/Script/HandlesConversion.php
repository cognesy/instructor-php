<?php

namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Utils\Messages\Messages;
use Exception;
use RuntimeException;

trait HandlesConversion
{
    /**
     * @param array<string> $order
     * @return Messages
     */
    public function toMessages(array $parameters = null) : Messages {
        $messages = new Messages();
        foreach ($this->sections as $section) {
            $content = match(true) {
                $section->isTemplate() => $this->fromTemplate(
                    name: $section->name(),
                    parameters: $this->parameters()->merge($parameters)->toArray(),
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
     * @param array<string,mixed>|null $parameters
     * @return array<string,string|array>
     */
    public function toArray(array $parameters = null, bool $raw = false) : array {
        $array = $this->toMessages()->toArray();

        return match($raw) {
            false => $this->renderMessages(
                messages: $array,
                parameters: $this->parameters()->merge($parameters)->toArray()),
            true => $array,
        };
    }

    /**
     * @param array<string> $order
     * @param string $separator
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    public function toString(string $separator = "\n", array $parameters = null) : string {
        if ($this->hasComposites()) {
            throw new RuntimeException('Script contains composite messages and cannot be converted to string.');
        }
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
            parameters: $this->parameters()->merge($parameters)->toArray()
        );
    }

    // INTERNAL ////////////////////////////////////////////////////

    protected function fromTemplate(string $name, ?array $parameters) : Messages {
        if (empty($parameters)) {
            return new Messages();
        }

        $source = $parameters[$name] ?? throw new Exception("Parameter does not have value: $name");

        // process parameter
        $values = match(true) {
            is_callable($source) => $source($parameters),
            is_array($source) => Messages::fromArray($source),
            $source instanceof Messages => $source,
            is_string($source) => Messages::fromString($source),
            default => throw new Exception("Invalid template value: $name"),
        };

        // process results of callable parameter
        return match(true) {
            $values instanceof Messages => $values,
            is_array($values) => Messages::fromArray($values),
            is_string($values) => Messages::fromString($values),
            default => throw new Exception("Invalid template value: $name"),
        };
    }
}