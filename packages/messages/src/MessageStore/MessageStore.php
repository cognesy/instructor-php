<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\Traits\MessageStore\HandlesAccess;
use Cognesy\Messages\MessageStore\Traits\MessageStore\HandlesConversion;
use Cognesy\Messages\MessageStore\Traits\MessageStore\HandlesCreation;
use Cognesy\Messages\MessageStore\Traits\MessageStore\HandlesMutation;
use Cognesy\Messages\MessageStore\Traits\MessageStore\HandlesParameters;
use Cognesy\Messages\MessageStore\Traits\MessageStore\HandlesTransformation;

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
    use HandlesAccess;
    use HandlesParameters;
    use HandlesConversion;
    use HandlesCreation;
    use HandlesMutation;
    //use HandlesReordering;
    use HandlesTransformation;

    public Sections $sections;
    public MessageStoreParameters $parameters;

    public function __construct(
        ?Sections $sections = null,
        ?MessageStoreParameters $parameters = null,
    ) {
        $this->sections = $sections ?? new Sections();
        $this->parameters = $parameters ?? new MessageStoreParameters();
    }

    public static function fromSections(Section ...$sections): static {
        return new static(new Sections(...$sections));
    }

    public static function fromMessages(Messages $messages, string $section = 'messages') : MessageStore {
        $sections = new Sections((new Section($section))->appendMessages($messages));
        return new self($sections);
    }

    public function clone() : self {
        return (new MessageStore($this->sections))
            ->withParams($this->parameters());
    }
}
