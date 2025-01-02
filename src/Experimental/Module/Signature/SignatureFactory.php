<?php

namespace Cognesy\Instructor\Experimental\Module\Signature;

class SignatureFactory
{
    use \Cognesy\Instructor\Experimental\Module\Signature\Traits\Factory\CreatesFromCallable;
    use \Cognesy\Instructor\Experimental\Module\Signature\Traits\Factory\CreatesFromClasses;
    use \Cognesy\Instructor\Experimental\Module\Signature\Traits\Factory\CreatesFromRequest;
    use \Cognesy\Instructor\Experimental\Module\Signature\Traits\Factory\CreatesSignatureFromString;
    use \Cognesy\Instructor\Experimental\Module\Signature\Traits\Factory\CreatesFromStructure;
    use \Cognesy\Instructor\Experimental\Module\Signature\Traits\Factory\CreatesFromClassMetadata;
}
