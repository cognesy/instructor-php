<?php
namespace Cognesy\Instructor\Extras\Module\CallData\Contracts;

use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;

interface HasInputOutputData extends HasSignature
{
    public function withArgs(mixed ...$args) : static;

    public function input() : DataAccess;

    public function output() : DataAccess;

    public function toArray() : array;
}
