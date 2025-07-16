<?php declare(strict_types=1);
namespace Cognesy\Template\Script;

use Cognesy\Template\Script\Traits\RendersContent;

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
    use Traits\Script\HandlesAccess;
    use Traits\Script\HandlesParameters;
    use Traits\Script\HandlesConversion;
    use Traits\Script\HandlesCreation;
    use Traits\Script\HandlesMutation;
    use Traits\Script\HandlesReordering;
    use Traits\Script\HandlesTransformation;
    use RendersContent;

    /** @var Section[] */
    private array $sections;

    public function __construct(Section ...$sections) {
        $this->sections = $sections;
        $this->parameters = new ScriptParameters(null);
    }
}
