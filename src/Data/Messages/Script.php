<?php
namespace Cognesy\Instructor\Data\Messages;

class Script {
    use Traits\Script\HandlesAccess;
    use Traits\Script\HandlesContext;
    use Traits\Script\HandlesConversion;
    use Traits\Script\HandlesCreation;
    use Traits\Script\HandlesMutation;
    use Traits\Script\HandlesReordering;
    use Traits\Script\HandlesTransformation;
    use Traits\RendersTemplates;

    /** @var Section[] */
    private array $sections;

    public function __construct(Section ...$sections) {
        $this->sections = $sections;
        $this->context = new ScriptContext(null);
    }
}
