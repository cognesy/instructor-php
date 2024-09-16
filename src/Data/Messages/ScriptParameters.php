<?php

namespace Cognesy\Instructor\Data\Messages;

class ScriptParameters
{
    use Traits\ScriptParameters\HandlesAccess;
    use Traits\ScriptParameters\HandlesConversion;
    use Traits\ScriptParameters\HandlesMutation;
    use Traits\ScriptParameters\HandlesTransformation;

    private array $parameters;

    public function __construct(?array $parameters) {
        $this->parameters = $parameters ?? [];
    }
}
