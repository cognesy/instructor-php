<?php
namespace Cognesy\Utils\Messages;

use Cognesy\Utils\Messages\Traits\Section\HandlesAccess;
use Cognesy\Utils\Messages\Traits\Section\HandlesConversion;
use Cognesy\Utils\Messages\Traits\Section\HandlesHeaderFooter;
use Cognesy\Utils\Messages\Traits\Section\HandlesMetadata;
use Cognesy\Utils\Messages\Traits\Section\HandlesMutation;
use Cognesy\Utils\Messages\Traits\Section\HandlesTransformation;

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
    use HandlesAccess;
    use HandlesConversion;
    use HandlesHeaderFooter;
    use HandlesMetadata;
    use HandlesMutation;
    use HandlesTransformation;

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
