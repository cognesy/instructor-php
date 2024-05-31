<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Contracts;

use Cognesy\Instructor\Extras\Module\DataModel\Contracts\DataModel;

interface HasInputOutputData extends HasErrorData
{
    // DATA ENTRY /////////////////////////////////////////////////////////

    public function withArgs(mixed ...$inputs) : static;

    // DATA MODEL ACCESS //////////////////////////////////////////////////

    public function input() : DataModel;
    public function output() : DataModel;

    public function toArray() : array;
}