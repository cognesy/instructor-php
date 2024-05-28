<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

class SignatureFactory
{
    use \Cognesy\Instructor\Extras\Tasks\Signature\Traits\CreatesFromCallable;
    use \Cognesy\Instructor\Extras\Tasks\Signature\Traits\CreatesFromClasses;
    use \Cognesy\Instructor\Extras\Tasks\Signature\Traits\CreatesFromClassMetadata;
    use \Cognesy\Instructor\Extras\Tasks\Signature\Traits\CreatesSignatureFromString;
    use \Cognesy\Instructor\Extras\Tasks\Signature\Traits\CreatesFromStructure;
}