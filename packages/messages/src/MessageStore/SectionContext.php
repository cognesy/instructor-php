<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Context object that allows fluent section operations while maintaining immutability
 */
class SectionContext
{
    public function __construct(
        private MessageStore $store,
        private string $sectionName,
    ) {}

    public function appendMessage(array|Message $message): MessageStore {
        return $this->script->appendMessageToSection($this->sectionName, $message);
    }

    public function appendMessages(Messages $messages): MessageStore {
        $store = $this->script;
        foreach ($messages->each() as $message) {
            $store = $store->appendMessageToSection($this->sectionName, $message);
        }
        return $store;
    }

    public function prependMessage(array|Message $message): MessageStore {
        return $this->script->prependMessageToSection($this->sectionName, $message);
    }

    public function section(string $name): SectionContext {
        return new SectionContext($this->script, $name);
    }

    public function script(): MessageStore {
        return $this->script;
    }
}