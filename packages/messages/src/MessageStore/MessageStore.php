<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\Operators\ParameterOperator;
use Cognesy\Messages\MessageStore\Operators\SectionOperator;
use Cognesy\Utils\Metadata;

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
    public Sections $sections;
    public Metadata $parameters;

    public function __construct(
        ?Sections $sections = null,
        ?Metadata $parameters = null,
    ) {
        $this->sections = $sections ?? new Sections();
        $this->parameters = $parameters ?? new Metadata();
    }

    // CONSTRUCTORS ///////////////////////////////////////////

    public static function empty() : MessageStore {
        return new MessageStore();
    }

    public static function fromSections(Section ...$sections): MessageStore {
        return new MessageStore(new Sections(...$sections));
    }

    public static function fromMessages(Messages $messages, string $section = 'messages') : MessageStore {
        $sections = new Sections((new Section($section))->appendMessages($messages));
        return new MessageStore($sections);
    }

    // MUTATORS ///////////////////////////////////////////////

    public function withSection(string $name): MessageStore {
        if ($this->section($name)->exists()) {
            return $this;
        }

        $newSection = new Section($name);
        $newSections = $this->sections->add($newSection);
        return new MessageStore(
            sections: $newSections,
            parameters: $this->parameters,
        );
    }

    public function setSection(Section $section): MessageStore {
        return new MessageStore(
            sections: $this->sections->set($section),
            parameters: $this->parameters,
        );
    }

    public function removeSection(string $name): MessageStore {
        if (!$this->sections->has($name)) {
            return $this;
        }
        return new MessageStore(
            sections: $this->sections->filter(fn($section) => $section->name !== $name),
            parameters: $this->parameters,
        );
    }

    public function merge(MessageStore $other): MessageStore {
        return new MessageStore(
            sections: $this->sections->merge($other->sections()),
            parameters: $this->parameters->withMergedData($other->parameters->toArray()),
        );
    }

    public function withoutEmpty(): MessageStore {
        return new MessageStore(
            sections: $this->sections->withoutEmpty(),
            parameters: $this->parameters,
        );
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function sections() : Sections {
        return $this->sections;
    }

    // CONVERSIONS and TRANSFORMATIONS //////////////////////////

    /**
     * @param string|string[] $sections
     */
    public function select(string|array $sections = []) : MessageStore {
        $names = match (true) {
            empty($sections) => [],
            is_string($sections) => [$sections],
            is_array($sections) => $sections,
        };
        return new MessageStore(
            sections: $this->sections->select($names),
            parameters: $this->parameters,
        );
    }

    public function toMessages() : Messages {
        return $this->sections->toMessages();
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function toFlatArray() : array {
        return $this->toMessages()->toArray();
    }

    public function toString() : string {
        return $this->toMessages()->toString();
    }

    public function section(string $name) : SectionOperator {
        return new SectionOperator($this, $name);
    }

    public function parameters(): ParameterOperator {
        return new ParameterOperator($this);
    }

    // SERIALIZATION ////////////////////////////////////////

    /**
     * @return array{
     *     sections: list<array{name: string, messages: list<array<array-key, mixed>>}>,
     *     parameters: array<string, mixed>
     * }
     */
    public function toArray() : array {
        return [
            'sections' => array_map(
                static fn(Section $section) => [
                    'name' => $section->name(),
                    'messages' => $section->messages()->toArray(),
                ],
                $this->sections->all(),
            ),
            'parameters' => $this->parameters->toArray(),
        ];
    }

    public static function fromArray(array $data) : self {
        $sections = isset($data['sections']) ? Sections::fromArray($data['sections']) : new Sections();
        $parameters = isset($data['parameters']) ? Metadata::fromArray($data['parameters']) : new Metadata();
        return new MessageStore(
            sections: $sections,
            parameters: $parameters,
        );
    }

    // STORAGE INTEGRATION ////////////////////////////////////

    /**
     * Load MessageStore from storage.
     */
    public static function fromStorage(
        Contracts\CanStoreMessages $storage,
        string $sessionId,
    ): self {
        return $storage->load($sessionId);
    }

    /**
     * Save MessageStore to storage.
     */
    public function toStorage(
        Contracts\CanStoreMessages $storage,
        string $sessionId,
    ): Data\StoreMessagesResult {
        return $storage->save($sessionId, $this);
    }
}
