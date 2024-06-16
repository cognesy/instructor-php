<?php
namespace Cognesy\Instructor\Extras\Module\Core;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;

abstract class Module extends BaseModule
{
    public function signature() : string|Signature {
        return SignatureFactory::fromCallable($this->forward(...));
    }

    // INTERNAL /////////////////////////////////////////////////////////////

}
