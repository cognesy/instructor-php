<?php

namespace Cognesy\Utils\Messages\Traits\Messages;

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use RuntimeException;

trait HandlesConversion
{
    /**
     * @param array<string, array<string|array>> $messages
     * @return array<string, array<string|array>>
     */
    public static function asPerRoleArray(array $messages) : array {
        if (empty($messages)) {
            return ['role' => 'user', 'content' => ''];
        }

        $role = 'user';
        $merged = new Messages();
        $content = [];
        foreach ($messages as $message) {
            if ($role !== $message['role'] || Message::becomesComposite($message)) {
                $merged->appendMessage(new Message(
                    role: $role,
                    content: implode("\n\n", array_filter($content)),
                ));
                $role = $message['role'];
                $content = [];

                if (Message::becomesComposite($message)) {
                    $merged->appendMessage($message);
                    continue;
                }
            }
            $content[] = $message['content'];
        }
        // append remaining content
        if (!empty($content)) {
            $merged->appendMessage(new Message(
                role: $role,
                content: implode("\n", array_filter($content)), // TODO: see above
                metadata: $message['_metadata'] ?? [],
            ));
        }
        return $merged->toArray();
    }

    public static function asString(
        array $messages,
        string $separator = "\n",
        callable $renderer = null
    ) : string {
        $result = '';
        foreach ($messages as $message) {
            if (empty($message) || !is_array($message) || empty($message['content'])) {
                continue;
            }
            $rendered = match(true) {
                !is_null($renderer) => $renderer($message),
                default => match(true) {
                    Message::becomesComposite($message) => throw new RuntimeException('Array contains composite messages, cannot be converted to string.'),
                    default => $message['content'] . $separator,
                }
            };
            $result .= $rendered;
        }
        return $result;
    }

    /**
     * @return array<string, string|array>
     */
    public function toArray() : array {
        $result = [];
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $result[] = $message->toArray();
        }
        return $result;
    }

    public function toString(string $separator = "\n") : string {
        if ($this->hasComposites()) {
            throw new RuntimeException('Collection contains composite messages and cannot be converted to string.');
        }
        return self::asString($this->toArray(), $separator);
    }
}