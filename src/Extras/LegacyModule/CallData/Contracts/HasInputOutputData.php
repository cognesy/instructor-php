<?php
namespace Cognesy\Instructor\Extras\Module\CallData\Contracts;

use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;

interface HasInputOutputData extends \Cognesy\Experimental\Module\Signature\Contracts\HasSignature
{
    public function withArgs(mixed ...$args) : static;

    public function input() : DataAccess;

    public function output() : DataAccess;

    public function toArray() : array;
}
