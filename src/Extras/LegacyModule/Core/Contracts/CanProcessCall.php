<?php

namespace Cognesy\Instructor\Extras\Module\Core\Contracts;

use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;

interface CanProcessCall
{
    /**
     * Initiates new module execution for the module with the provided data.
     * The execution is not until any value is retrieved via the pending
     * execution object.
     *
     * @param HasInputOutputData $data
     * @return HasPendingExecution
     */
    public function withArgs(mixed ...$args) : HasPendingExecution;

    /**
     * Initiates new module execution for the module with the provided
     * CallData object.
     * The execution is not until any value is retrieved via the pending
     * execution object.
     *
     * @param HasInputOutputData $data
     * @return HasPendingExecution
     */
    public function with(HasInputOutputData $data) : HasPendingExecution;
}
