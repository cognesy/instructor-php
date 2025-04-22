<?php
namespace Cognesy\Addons\Evals\Contracts;

use Cognesy\Addons\Evals\Execution;

interface CanRunExecution
{
    public function run(Execution $execution): Execution;
}