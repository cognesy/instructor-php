<?php declare(strict_types=1);
namespace Cognesy\Template\Script;

use Cognesy\Messages\Messages;
use Cognesy\Template\Script\Traits\Section\HandlesAccess;
use Cognesy\Template\Script\Traits\Section\HandlesConversion;
use Cognesy\Template\Script\Traits\Section\HandlesHeaderFooter;
use Cognesy\Template\Script\Traits\Section\HandlesMetadata;
use Cognesy\Template\Script\Traits\Section\HandlesMutation;
use Cognesy\Template\Script\Traits\Section\HandlesTransformation;

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
final readonly class Section {
    use HandlesAccess;
    use HandlesConversion;
    use HandlesHeaderFooter;
    use HandlesMetadata;
    use HandlesMutation;
    use HandlesTransformation;

    public const MARKER = '@';
    
    public string $name;
    public string $description;
    public array $metadata;
    public Messages $messages;
    public Messages $header;
    public Messages $footer;

    public function __construct(
        string $name,
        string $description = '',
        array $metadata = [],
        ?Messages $messages = null,
        ?Messages $header = null,
        ?Messages $footer = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->metadata = $metadata;
        $this->messages = $messages ?? Messages::empty();
        $this->header = $header ?? Messages::empty();
        $this->footer = $footer ?? Messages::empty();
    }

    public function isTemplate(): bool {
        return str_starts_with($this->name, self::MARKER);
    }
}
