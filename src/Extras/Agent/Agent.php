<?php

namespace Cognesy\Instructor\Extras\Agent;

class Agent
{
    use Traits\Agent\HandlesMemory;
    use Traits\Agent\HandlesPersona;
    use Traits\Agent\HandlesTasks;
    use Traits\Agent\HandlesTools;

    private string $name;
    private string $description;
    private string $goals;
}
