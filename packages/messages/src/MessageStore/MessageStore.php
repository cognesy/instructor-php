<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\Traits\MessageStore\HandlesParameters;

/**
 * MessageStore represents a library of message sequences with multiple sections and messages.
 * It is used to interact with chat-type language models to provide them instructions
 * and replay the history of interaction.
 *
 * Think of it like a library, where each section is a set of messages. MessageStore offers
 * way to compile all or selected section messages into a single sequence, or to manipulate
 * the sections.
 */
final readonly class MessageStore
{
    use HandlesParameters;

    public Sections $sections;
    public MessageStoreParameters $parameters;

    public function __construct(
        ?Sections $sections = null,
        ?MessageStoreParameters $parameters = null,
    ) {
        $this->sections = $sections ?? new Sections();
        $this->parameters = $parameters ?? new MessageStoreParameters();
    }

    // CONSTRUCTORS

    public static function fromSections(Section ...$sections): static {
        return new static(new Sections(...$sections));
    }

    public static function fromMessages(Messages $messages, string $section = 'messages') : MessageStore {
        $sections = new Sections((new Section($section))->appendMessages($messages));
        return new self($sections);
    }

    // MUTATORS

    public function withSection(string $name): static {
        if ($this->section($name)->exists()) {
            return $this;
        }

        $newSection = new Section($name);
        $newSections = $this->sections->add($newSection);
        return new static(
            sections: $newSections,
            parameters: $this->parameters,
        );
    }

    // ACCESSORS / CONVERSIONS

    public function sections() : Sections {
        return $this->sections;
    }

    /**
     * @param string|string[] $sections
     */
    public function select(string|array $sections = []) : static {
        $names = match (true) {
            empty($sections) => $this->sections->map(fn($section) => $section->name),
            is_string($sections) => [$sections],
            is_array($sections) => $sections,
        };
        $selectedSections = [];
        foreach ($names as $sectionName) {
            if ($this->section($sectionName)->exists()) {
                $selectedSections[] = $this->section($sectionName)->get();
            }
        }
        return new static(
            sections: new Sections(...$selectedSections),
            parameters: $this->parameters,
        );
    }

    public function toMessages() : Messages {
        return $this->sections->toMessages();
    }

    /**
     * @return array<string,array>
     */
    public function toArray() : array {
        return $this->toMessages()->toArray();
    }

    public function toString() : string {
        return $this->toMessages()->toString();
    }

    /**
     * Get a fluent section operator for the given section
     * 
     * Usage:
     * $store->applyTo('system')->appendMessages($messages)
     * $store->applyTo('prompt')->replaceMessages($messages)
     * $store->applyTo('examples')->remove()
     */
    public function applyTo(string $sectionName): SectionMutator {
        return new SectionMutator($this, $sectionName);
    }

    /**
     * Get a fluent section accessor for the given section
     * Usage:
     * $store->section('system')->get()
     * $store->section('prompt')->exists()
     * $store->section('examples')->isEmpty()
     */
    public function section(string $name) : SectionAccessor {
        return new SectionAccessor($this, $name);
    }
}
