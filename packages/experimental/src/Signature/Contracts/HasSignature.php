<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Contracts;

use Cognesy\Experimental\Signature\Signature;

interface HasSignature
{
    public function signature() : Signature;
}
