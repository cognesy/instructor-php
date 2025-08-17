<?php declare(strict_types=1);
namespace Cognesy\Evals\Contracts;

use Cognesy\Evals\Execution;

interface CanRunExecution
{
    public function run(Execution $execution): Execution;
}