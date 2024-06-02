<?php
namespace Cognesy\Instructor\Extras\Agent;

use Closure;
use Cognesy\Instructor\Extras\FunctionCall\FunctionCall;
use Cognesy\Instructor\Instructor;

class Tool
{
    use Traits\Tool\HandleToolInfo;
    use Traits\Tool\HandlesSchemas;
    use Traits\Tool\HandlesExtraction;
    use Traits\Tool\HandlesExecution;

    public function __construct(
        string $name,
        string $description,
        callable $function,
        Instructor $instructor = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->call = FunctionCall::fromCallable($function);
        $this->function = Closure::fromCallable($function);
        $this->instructor = $instructor ?? new Instructor();
    }
}
