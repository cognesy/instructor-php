<?php
namespace Cognesy\Instructor\Experimental\Module\Core;

use AllowDynamicProperties;
use Closure;

#[AllowDynamicProperties]
class ModuleCall
{
    use Traits\ModuleCall\HandlesDynamicProperties;
    use Traits\ModuleCall\HandlesErrors;
    use Traits\ModuleCall\HandlesExamples;
    use Traits\ModuleCall\HandlesExecution;
    use Traits\ModuleCall\HandlesInputs;
    use Traits\ModuleCall\HandlesOutputs;

    protected array $inputs = [];
    protected ?array $outputs = null;
    protected Closure $moduleCall;
    protected array $errors = [];

    public function __construct(
        array $inputs = [],
        Closure $moduleCall = null,
    ) {
        $this->inputs = $inputs;
        $this->moduleCall = $moduleCall;
    }
}
