<?php
namespace Cognesy\Instructor\Utils\Messages;

/**
 * Represents a distinct named section of message sequence (script).
 * It can be used to manage related groups of messages, e.g.
 * system section, prompt section, actual chat, summarized entries
 * pruned from the chat, etc.
 *
 * It can have custom header messages, and footer messages.
 *
 * The Section is initialized with a name, description, and metadata,
 * and determines its template status during instantiation.
 */
class Section {
    use Traits\Section\HandlesAccess;
    use Traits\Section\HandlesConversion;
    use Traits\Section\HandlesHeaderFooter;
    use Traits\Section\HandlesMetadata;
    use Traits\Section\HandlesMutation;
    use Traits\Section\HandlesTransformation;

    public const MARKER = '@';
    private Messages $messages;
    private Messages $header;
    private Messages $footer;
    private bool $isTemplate = false;

    public function __construct(
        public string $name,
        public string $description = '',
        public array $metadata = [],
    ) {
        if (str_starts_with($name, self::MARKER)) {
            $this->isTemplate = true;
        }
        $this->messages = new Messages();
        $this->header = new Messages();
        $this->footer = new Messages();
    }
}
