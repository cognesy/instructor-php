<?php

namespace Cognesy\Instructor\Extras\Tool;

use Closure;
use Cognesy\Instructor\Extras\Call\Call;
use Cognesy\Instructor\Instructor;

class Tool
{
    use Traits\HandleToolInfo;
    use Traits\HandlesSchemas;
    use Traits\HandlesExtraction;
    use Traits\HandlesExecution;

    public function __construct(
        string $name,
        string $description,
        callable $function,
        Instructor $instructor = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->call = Call::fromCallable($function);
        $this->function = Closure::fromCallable($function);
        $this->instructor = $instructor ?? new Instructor();
    }
}
