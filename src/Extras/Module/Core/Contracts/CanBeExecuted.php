<?php

namespace Cognesy\Instructor\Extras\Module\Core\Contracts;

interface CanBeExecuted
{
    // public function __construct();
    public function boot() : void;

    // execute and return the result
    public function with(mixed $input) : mixed;

    // method defining processing logic
    public function forward(mixed ...$input) : mixed;

    // methods for stepped execution: set() > result() | output()
    public function set(mixed ...$input) : static;
    // returns result provider object
    public function result() : mixed;

    // returns task
    public function asTask() : Task;
    // returns standardized output = array of key-value pairs as defined in task signature
    public function asOutputs() : array;
    // returns raw results of forward() method
    public function raw() : mixed;
}
