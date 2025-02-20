<?php

namespace Cognesy\Experimental\Module\Signature;

use Cognesy\Experimental\Module\Signature\Traits\Factory\CreatesFromCallable;
use Cognesy\Experimental\Module\Signature\Traits\Factory\CreatesFromClasses;
use Cognesy\Experimental\Module\Signature\Traits\Factory\CreatesFromClassMetadata;
use Cognesy\Experimental\Module\Signature\Traits\Factory\CreatesFromRequest;
use Cognesy\Experimental\Module\Signature\Traits\Factory\CreatesFromStructure;
use Cognesy\Experimental\Module\Signature\Traits\Factory\CreatesSignatureFromString;

class SignatureFactory
{
    use CreatesFromCallable;
    use CreatesFromClasses;
    use CreatesFromRequest;
    use CreatesSignatureFromString;
    use CreatesFromStructure;
    use CreatesFromClassMetadata;
}
