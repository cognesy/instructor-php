<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Feedback\Feedback;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
interface CanCritiqueExecution
{
    public function critique(Execution $execution) : Feedback;
}