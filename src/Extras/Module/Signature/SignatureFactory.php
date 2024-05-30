<?php

namespace Cognesy\Instructor\Extras\Module\Signature;

class SignatureFactory
{
    use Traits\Factory\CreatesFromCallable;
    use Traits\Factory\CreatesFromClasses;
    use Traits\Factory\CreatesSignatureFromString;
    use Traits\Factory\CreatesFromStructure;
}
