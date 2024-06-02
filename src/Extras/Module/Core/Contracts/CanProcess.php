<?php

namespace Cognesy\Instructor\Extras\Module\Core\Contracts;

use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;

interface CanProcess
{
    // methods for stepped execution: set() > result() | output()
    public function withArgs(mixed ...$args) : HasPendingExecution;

    // execute and return the result
    public function with(HasInputOutputData $data) : HasPendingExecution;

    // method defining processing logic
    //public function forward(mixed... $args) : mixed;
}
