<?php declare(strict_types=1);

namespace Cognesy\Messages\Script;

use Cognesy\Messages\Script\Traits\Script\HandlesAccess;
use Cognesy\Messages\Script\Traits\Script\HandlesConversion;
use Cognesy\Messages\Script\Traits\Script\HandlesCreation;
use Cognesy\Messages\Script\Traits\Script\HandlesMutation;
use Cognesy\Messages\Script\Traits\Script\HandlesParameters;
use Cognesy\Messages\Script\Traits\Script\HandlesReordering;
use Cognesy\Messages\Script\Traits\Script\HandlesTransformation;

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
final readonly class Script
{
    use HandlesAccess;
    use HandlesParameters;
    use HandlesConversion;
    use HandlesCreation;
    use HandlesMutation;
    use HandlesReordering;
    use HandlesTransformation;

    /** @var Section[] */
    public array $sections;
    public ScriptParameters $parameters;

    public function __construct(
        array $sections = [],
        ?ScriptParameters $parameters = null,
    ) {
        $this->sections = $sections;
        $this->parameters = $parameters ?? new ScriptParameters();
    }

    public static function fromSections(Section ...$sections): static {
        return new static($sections);
    }
}
