<?php
namespace Cognesy\Utils\Messages;

use Cognesy\Utils\Messages\Traits\RendersContent;
use Cognesy\Utils\Messages\Traits\Script\HandlesAccess;
use Cognesy\Utils\Messages\Traits\Script\HandlesConversion;
use Cognesy\Utils\Messages\Traits\Script\HandlesCreation;
use Cognesy\Utils\Messages\Traits\Script\HandlesMutation;
use Cognesy\Utils\Messages\Traits\Script\HandlesParameters;
use Cognesy\Utils\Messages\Traits\Script\HandlesReordering;
use Cognesy\Utils\Messages\Traits\Script\HandlesTransformation;

/**
 * Script represents a complex message sequence with multiple sections and messages.
 * It is used to interact with chat-type language models to provide them instructions
 * and replay the history of interaction.
 *
 * Think of it like a script in a play, where each section is a scene, and messages
 * are dialogues or stage directions.
 *
 * Script provides various convenience methods to manipulate the sequence of messages,
 * such as adding, removing, reordering, transforming, and converting sections and
 * individual messages.
 */
class Script {
    use HandlesAccess;
    use HandlesParameters;
    use HandlesConversion;
    use HandlesCreation;
    use HandlesMutation;
    use HandlesReordering;
    use HandlesTransformation;
    use RendersContent;

    /** @var Section[] */
    private array $sections;

    public function __construct(Section ...$sections) {
        $this->sections = $sections;
        $this->parameters = new ScriptParameters(null);
    }
}
