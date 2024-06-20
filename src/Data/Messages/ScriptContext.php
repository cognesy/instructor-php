<?php

namespace Cognesy\Instructor\Data\Messages;

class ScriptContext
{
    use Traits\ScriptContext\HandlesAccess;
    use Traits\ScriptContext\HandlesConversion;
    use Traits\ScriptContext\HandlesMutation;
    use Traits\ScriptContext\HandlesTransformation;

    private array $context;

    public function __construct(?array $context) {
        $this->context = $context ?? [];
    }
}
