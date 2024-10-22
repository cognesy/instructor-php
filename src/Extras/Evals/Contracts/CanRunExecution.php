<?php
namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Execution;

interface CanRunExecution
{
    public function run(Execution $execution): Execution;
}