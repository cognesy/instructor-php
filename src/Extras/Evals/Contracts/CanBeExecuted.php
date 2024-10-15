<?php
namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Execution;

interface CanBeExecuted
{
    public function execute(Execution $execution): Execution;
}