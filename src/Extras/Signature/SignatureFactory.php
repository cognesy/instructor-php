<?php

namespace Cognesy\Instructor\Extras\Signature;

class SignatureFactory
{
    use Traits\CreatesFromCallable;
    use Traits\CreatesFromClasses;
    use Traits\CreatesFromClassMetadata;
    use Traits\CreatesSignatureFromString;
    use Traits\CreatesFromStructure;
}