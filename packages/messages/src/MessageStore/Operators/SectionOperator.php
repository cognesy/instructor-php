<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Operators;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

/**
 * Get a fluent section accessor for the given section
 * Usage:
 * $store->section('system')->get()
 * $store->section('prompt')->exists()
 * $store->section('examples')->isEmpty()
 * $store->section('system')->appendMessages($messages)
 * $store->section('prompt')->replaceMessages($messages)
 * $store->section('examples')->remove()
 */
final readonly class SectionOperator
{
    private MessageStore $store;
    private string $sectionName;

    public function __construct(
        MessageStore $store,
        string $sectionName,
    ) {
        $this->store = $store;
        $this->sectionName = $sectionName;
    }

    // ACCESSORS //////////////////////////////////////////////////

    public function name(): string {
        return $this->sectionName;
    }

    public function get(): Section {
        if (!$this->store->sections()->has($this->sectionName)) {
            return Section::empty($this->sectionName);
        }
        $section = $this->store->sections()->get($this->sectionName);
        if ($section === null) {
            return Section::empty($this->sectionName);
        }
        return $section;
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

    // MUTATORS //////////////////////////////////////////////////

    public function appendMessages(array|Message|Messages $messages): MessageStore {
        $messages = Messages::fromAny($messages);
        if ($messages->isEmpty()) {
            return $this->store;
        }
        return $this->store->setSection(
            $this->get()->appendMessages($messages)
        );
    }

    public function setSection(Section $section) : MessageStore {
        // rename section if needed
        $newSection = match(true) {
            ($section->name !== $this->sectionName) => new Section($this->sectionName, messages: $section->messages()),
            default => $section,
        };
        return $this->store->setSection($newSection);
    }

    public function setMessages(array|Message|Messages $messages): MessageStore {
        $messages = Messages::fromAny($messages);
        $section = new Section($this->sectionName, messages: $messages);
        return $this->store->setSection($section);
    }

    public function remove(): MessageStore {
        return $this->store->removeSection($this->sectionName);
    }

    public function clear(): MessageStore {
        return $this->setMessages(Messages::empty());
    }
}