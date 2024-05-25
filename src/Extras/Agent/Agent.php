<?php

namespace Cognesy\Instructor\Extras\Agent;

use Cognesy\Instructor\Extras\Toolset\Toolset;

class Agent
{
    use Traits\HandlesPersona;
    use Traits\HandlesMemory;
    use Traits\HandlesTasks;
    use Traits\HandlesTools;

    private string $name;
    private string $description;
    private string $goals;
}
