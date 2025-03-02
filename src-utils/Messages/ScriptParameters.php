<?php

namespace Cognesy\Utils\Messages;

use Cognesy\Utils\Messages\Traits\ScriptParameters\HandlesAccess;
use Cognesy\Utils\Messages\Traits\ScriptParameters\HandlesConversion;
use Cognesy\Utils\Messages\Traits\ScriptParameters\HandlesMutation;
use Cognesy\Utils\Messages\Traits\ScriptParameters\HandlesTransformation;

class ScriptParameters
{
    use HandlesAccess;
    use HandlesConversion;
    use HandlesMutation;
    use HandlesTransformation;

    private array $parameters;

    public function __construct(?array $parameters) {
        $this->parameters = $parameters ?? [];
    }
}
