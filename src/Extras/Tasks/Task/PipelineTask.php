<?php

namespace Cognesy\Instructor\Extras\Tasks\Task;

use Cognesy\Instructor\Utils\Pipeline;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated(reason: 'Needs revision')]
class PipelineTask extends ExecutableTask
{
    private Pipeline $pipeline;

    public function __construct(
        Pipeline $pipeline
    ) {
        parent::__construct();
        $this->pipeline = $pipeline;
    }

    public function forward(mixed $input): mixed {
        return $this->pipeline->process($input);
    }
}
