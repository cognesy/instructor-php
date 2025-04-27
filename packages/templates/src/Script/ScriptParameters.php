<?php

namespace Cognesy\Template\Script;

use Cognesy\Template\Script\Traits\ScriptParameters\HandlesAccess;
use Cognesy\Template\Script\Traits\ScriptParameters\HandlesConversion;
use Cognesy\Template\Script\Traits\ScriptParameters\HandlesMutation;
use Cognesy\Template\Script\Traits\ScriptParameters\HandlesTransformation;

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
