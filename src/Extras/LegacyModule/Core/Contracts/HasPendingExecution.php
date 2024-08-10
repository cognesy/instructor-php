<?php
namespace Cognesy\Instructor\Extras\Module\Core\Contracts;

use Cognesy\Instructor\Utils\Result\Result;

interface HasPendingExecution
{
    /**
     * Returns raw result of forward() method
     * or throws an exception if there are errors.
     * @return mixed
     */
    public function result() : mixed;

    /**
     * Returns Result object containing the result of forward() method
     * or errors if there are any. Does not throw the exception.
     * @return \Cognesy\Instructor\Utils\Result\Result
     */
    public function try() : Result;

    /**
     * Returns the value of the specified output property
     * or the entire output array if no name is provided.
     * array<string, mixed>
     */
    public function get(string $name = null) : mixed;

    /**
     * Returns true if there have been any errors during forward()
     * method execution.
     */
    public function hasErrors() : bool;

    /**
     * Returns an array of errors that occurred during forward()
     * method execution.
     */
    public function errors() : array;
}
