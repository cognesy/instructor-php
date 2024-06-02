<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\CallDataClass;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;

trait HandlesSignature
{
    public function signature(): Signature {
        if (!isset($this->signature)) {
            $this->signature = SignatureFactory::fromClassMetadata(static::class);
        }
        return $this->signature;
    }
}