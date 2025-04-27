<?php
namespace Cognesy\Utils\Messages\Traits\Messages;

trait HandlesTransformation
{
    public function toMergedPerRole() : \Cognesy\Utils\Messages\Messages {
        if ($this->isEmpty()) {
            return $this;
        }
        if ($this->hasComposites()) {
            $messages = $this->mergeRolesComposites();
        } else {
            $messages = $this->mergeRolesFlat();
        }
        return $messages;
    }

    public function forRoles(array $roles) : \Cognesy\Utils\Messages\Messages {
        $messages = new \Cognesy\Utils\Messages\Messages();
        foreach ($this->messages as $message) {
            if (in_array($message->role()->value, $roles)) {
                $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    public function exceptRoles(array $roles) : \Cognesy\Utils\Messages\Messages {
        $messages = new \Cognesy\Utils\Messages\Messages();
        foreach ($this->messages as $message) {
            if (!in_array($message->role()->value, $roles)) {
                $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    public function toRoleString() : string {
        $text = '';
        foreach ($this->messages as $message) {
            $text .= $message->toRoleString() . "\n";
        }
        return $text;
    }

    public function remapRoles(array $mapping) : \Cognesy\Utils\Messages\Messages {
        $messages = new \Cognesy\Utils\Messages\Messages();
        foreach ($this->messages as $message) {
            $role = $message->role()->value;
            $messages->appendMessage($message->withRole($mapping[$role] ?? $role));
        }
        return $messages;
    }

    public function reversed() : \Cognesy\Utils\Messages\Messages {
        $messages = new \Cognesy\Utils\Messages\Messages();
        $messages->messages = array_reverse($this->messages);
        return $messages;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    private function mergeRolesFlat() : \Cognesy\Utils\Messages\Messages {
        $role = $this->firstRole()->value;
        $messages = new \Cognesy\Utils\Messages\Messages();
        $content = [];
        foreach ($this->messages as $message) {
            if ($role !== $message->role()->value || $message->isComposite()) {
                $messages->appendMessage(new \Cognesy\Utils\Messages\Message(
                    role: $role,
                    content: implode("\n\n", array_filter($content)),
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
            $messages->appendMessage(new \Cognesy\Utils\Messages\Message(
                role: $role,
                content: implode("\n", array_filter($content)),
            ));
        }
        return $messages;
    }

    private function mergeRolesComposites() : \Cognesy\Utils\Messages\Messages {
        $composites = $this->toAllComposites();

        $messages = new \Cognesy\Utils\Messages\Messages();
        $role = $composites->firstRole()->value;
        $message = new \Cognesy\Utils\Messages\Message($role, []);
        foreach ($composites->all() as $composite) {
            if ($role !== $composite->role()->value) {
                $messages->appendMessage($message);
                $role = $composite->role()->value;
                $message = new \Cognesy\Utils\Messages\Message($role, []);
            }
            $message->addContentPart($composite->content(), $role);
        }
        $messages->appendMessage($message);
        return $messages;
    }

    private function toAllComposites() : \Cognesy\Utils\Messages\Messages {
        $messages = new \Cognesy\Utils\Messages\Messages();
        foreach ($this->messages as $message) {
            $messages->appendMessage(match(true) {
                $message->isComposite() => $message,
                default => $message->toCompositeMessage(),
            });
        }
        return $messages;
    }
}
