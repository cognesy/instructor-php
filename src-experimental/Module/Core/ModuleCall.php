<?php
namespace Cognesy\Experimental\Module\Core;

use AllowDynamicProperties;
use Closure;
use Cognesy\Experimental\Module\Core\Traits\ModuleCall\HandlesDynamicProperties;
use Cognesy\Experimental\Module\Core\Traits\ModuleCall\HandlesErrors;
use Cognesy\Experimental\Module\Core\Traits\ModuleCall\HandlesExamples;
use Cognesy\Experimental\Module\Core\Traits\ModuleCall\HandlesExecution;
use Cognesy\Experimental\Module\Core\Traits\ModuleCall\HandlesInputs;
use Cognesy\Experimental\Module\Core\Traits\ModuleCall\HandlesOutputs;

#[AllowDynamicProperties]
class ModuleCall
{
    use HandlesDynamicProperties;
    use HandlesErrors;
    use HandlesExamples;
    use HandlesExecution;
    use HandlesInputs;
    use HandlesOutputs;

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
