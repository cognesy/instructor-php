<?php
namespace Cognesy\Instructor\Experimental\Module\Signature\Contracts;

use Cognesy\Instructor\Experimental\Module\Signature\Signature;

interface HasSignature
{
    public function signature() : Signature;
}
