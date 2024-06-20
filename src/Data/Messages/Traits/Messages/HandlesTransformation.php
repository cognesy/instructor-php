<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;

trait HandlesTransformation
{
    public static function mergedPerRole(array $messages) : array {
        if (empty($messages)) {
            return ['role' => 'system', 'content' => ''];
        }

        $role = 'user';
        $merged = new Messages();
        $content = [];
        foreach ($messages as $message) {
            if ($role !== $message['role'] || is_array($message['content'])) {
                $merged->appendMessage(new Message(
                    role: $role,
                    content: implode("\n\n", array_filter($content)),
                ));
                $role = $message['role'];
                $content = [];

                if (is_array($message['content'])) {
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
                default => $message['content'] . $separator,
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
        return self::asString($this->toArray(), $separator);
    }

    public function toRoleString(string $role, string $separator = "\n") : string {
        $result = '';
        foreach ($this->messages as $message) {
            if ($message->isEmpty() || $message->role() !== $role) {
                continue;
            }
            $result .= $message->toRoleString() . $separator;
        }
        return $result;
    }

    public function toMergedPerRole() : Messages {
        if ($this->isEmpty()) {
            return $this;
        }
        $role = $this->firstRole()->value;
        $messages = new Messages();
        $content = [];
        foreach ($this->messages as $message) {
            if ($role !== $message->role()->value || $message->isComposite()) {
                $messages->appendMessage(new Message(
                    role: $role,
                    content: implode("\n\n", array_filter($content)), // TODO: check if content is array, needs different strategy then
                ));
                $role = $message->role()->value;
                $content = [];

                if ($message->isComposite()) {
                    $messages->appendMessage($message);
                    continue;
                }
            }
            $content[] = $message->content();
        }
        // append remaining content
        if (!empty($content)) {
            $messages->appendMessage(new Message(
                role: $role,
                content: implode("\n", array_filter($content)), // TODO: see above
            ));
        }
        return $messages;
    }
}
