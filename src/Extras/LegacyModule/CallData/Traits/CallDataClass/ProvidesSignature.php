<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\CallDataClass;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Experimental\Module\Signature\SignatureFactory;

trait ProvidesSignature
{
    private Signature $signature;

    public function signature(): Signature {
        if (!isset($this->signature)) {
            $this->signature = SignatureFactory::fromClassMetadata(static::class);
        }
        return $this->signature;
    }
}