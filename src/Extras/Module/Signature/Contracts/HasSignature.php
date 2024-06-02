<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Contracts;

use Cognesy\Instructor\Extras\Module\Signature\Signature;

interface HasSignature
{
    public function signature() : Signature;
}
