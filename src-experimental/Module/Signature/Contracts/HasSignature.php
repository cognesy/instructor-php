<?php
namespace Cognesy\Experimental\Module\Signature\Contracts;

use Cognesy\Experimental\Module\Signature\Signature;

interface HasSignature
{
    public function signature() : Signature;
}
