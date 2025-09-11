<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\Messages;

/**
 * Represents a distinct named section of message sequence.
 * It can be used to manage related groups of messages, e.g.
 * system section, prompt section, actual chat, summarized entries
 * pruned from the chat, etc.
 *
 * It can have custom header messages, and footer messages.
 *
 * The Section is initialized with a name, description, and metadata,
 * and determines its template status during instantiation.
 */
final readonly class Section
{
    public string $name;
    public Messages $messages;

    public function __construct(
        string $name,
        ?Messages $messages = null,
    ) {
        $this->name = $name;
        $this->messages = $messages ?? Messages::empty();
    }

    // CONSTRUCTORS ///////////////////////////////////////////

    public static function empty(string $name) : Section {
        return new static(name: $name);
    }

    // ACCESSORS //////////////////////////////////////////////

    public function name() : string {
        return $this->name;
    }

    public function isEmpty() : bool {
        return $this->messages->isEmpty();
    }

    public function messages() : Messages {
        return $this->messages;
    }

    // MUTATORS /////////////////////////////////////////////

        public function withMessages(Messages $messages) : Section {
        return new static(
            name: $this->name,
            messages: $messages,
        );
    }

    public function appendMessages(array|Messages $messages) : Section {
        return new static(
            name: $this->name,
            messages: $this->messages->appendMessages($messages),
        );
    }

    public function appendContentField(string $key, mixed $value) : Section {
        $lastMessage = $this->messages->last();
        $newContent = $lastMessage->content()->appendContentField($key, $value);
        $newMessage = $lastMessage->withContent($newContent);
        return new static(
            name: $this->name,
            messages: $this->messages->removeTail()->appendMessage($newMessage),
        );
    }

    // TRANSFORMATIONS / CONVERSIONS //////////////////////////

    /**
     * @return array<string,mixed>
     */
    public function toArray() : array {
        return $this->messages()->toArray();
    }

    public function toMergedPerRole() : Section {
        return (new Section($this->name()))
            ->appendMessages(
                $this->messages()->toMergedPerRole()
            );
    }

    public function withoutEmptyMessages() : Section {
        $section = new Section($this->name());
        $section = $section->withMessages($this->messages()->withoutEmptyMessages());
        return $section;
    }
}
