<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;

/**
 * Fluent API for MessageStore section operations
 * 
 * Usage:
 * $store->applyTo('system')->appendMessages($messages)
 * $store->applyTo('prompt')->replaceMessages($messages) 
 * $store->applyTo('examples')->remove()
 */
final readonly class SectionMutator
{
    private MessageStore $store;
    private string $sectionName;

    public function __construct(
        MessageStore $store,
        string $sectionName,
    ) {
        $this->sectionName = $sectionName;
        $this->store = $store;
    }

    /**
     * Append messages to the section
     */
    public function appendMessages(array|Message|Messages $messages): MessageStore {
        $messages = Messages::fromAny($messages);
        if ($messages->isEmpty()) {
            return $this->store;
        }
        $storeWithSection = $this->store->withSection($this->sectionName);
        $section = $storeWithSection->getSection($this->sectionName)->appendMessages($messages);
        return $this->withStore($storeWithSection)->replaceSection($section);
    }

    public function replaceSection(Section $section) : MessageStore {
        // rename section if needed
        $newSection = match(true) {
            ($section->name !== $this->sectionName) => new Section($this->sectionName, messages: $section->messages()),
            default => $section,
        };
        // rebuild the list of sections with new section replacing the old one
        $newSections = new Sections(...$this->store->sections()->map(
            fn($section) => $section->name === $this->sectionName ? $newSection : $section
        ));
        return new MessageStore(
            sections: $newSections,
            parameters: $this->store->parameters(),
        );
    }

    public function replaceMessages(array|Message|Messages $messages): MessageStore {
        $messages = Messages::fromAny($messages);
        $section = new Section($this->sectionName, messages: $messages);
        $storeWithSection = $this->store->withSection($this->sectionName);
        return $this->withStore($storeWithSection)->replaceSection($section);
    }

    /**
     * Remove the section entirely
     */
    public function remove(): MessageStore {
        if (!$this->store->hasSection($this->sectionName)) {
            return $this->store;
        }
        $newSections = $this->store->sections()->filter(fn($section) => $section->name !== $this->sectionName);
        return new MessageStore(
            sections: $newSections,
            parameters: $this->store->parameters(),
        );
    }

    public function clear(): MessageStore {
        return $this->replaceMessages(Messages::empty());
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function withStore(MessageStore $newStore) : self {
        return new self($newStore, $this->sectionName);
    }
}