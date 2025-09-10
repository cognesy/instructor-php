<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Operators;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

class SectionOperator {
    private MessageStore $store;
    private string $sectionName;

    public function __construct(
        MessageStore $store,
        string $sectionName,
    ) {
        $this->store = $store;
        $this->sectionName = $sectionName;
    }

    // ACCESSORS

    public function name(): string {
        return $this->sectionName;
    }

    public function get(): Section {
        if (!$this->store->sections()->has($this->sectionName)) {
            return Section::empty($this->sectionName);
        }
        return $this->store->sections()->get($this->sectionName);
    }

    public function exists(): bool {
        return $this->store->sections()->has($this->sectionName);
    }

    public function isEmpty(): bool {
        return $this->get()->isEmpty();
    }

    public function isNotEmpty(): bool {
        return !$this->isEmpty();
    }

    public function messages(): Messages {
        return $this->get()->messages();
    }

    // MUTATORS

    public function appendMessages(array|Message|Messages $messages): MessageStore {
        $messages = Messages::fromAny($messages);
        if ($messages->isEmpty()) {
            return $this->store;
        }
        $storeWithSection = $this->store->withSection($this->sectionName);
        $section = $storeWithSection
            ->section($this->sectionName)
            ->get()
            ->appendMessages($messages);
        return $this->withStore($storeWithSection)->setSection($section);
    }

    public function setSection(Section $section) : MessageStore {
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
            parameters: $this->store->parameters,
        );
    }

    public function setMessages(array|Message|Messages $messages): MessageStore {
        $messages = Messages::fromAny($messages);
        $section = new Section($this->sectionName, messages: $messages);
        $storeWithSection = $this->store->withSection($this->sectionName);
        return $this->withStore($storeWithSection)->setSection($section);
    }

    public function remove(): MessageStore {
        if (!$this->store->section($this->sectionName)->exists()) {
            return $this->store;
        }
        $newSections = $this->store->sections()->filter(fn($section) => $section->name !== $this->sectionName);
        return new MessageStore(
            sections: $newSections,
            parameters: $this->store->parameters,
        );
    }

    public function clear(): MessageStore {
        return $this->setMessages(Messages::empty());
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function withStore(MessageStore $newStore) : self {
        return new self($newStore, $this->sectionName);
    }
}