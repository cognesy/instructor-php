<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\CallData;

use Cognesy\Instructor\Extras\Module\Signature\Signature;

trait ProvidesSignature
{
    protected Signature $signature;

    public function signature(): Signature {
        return $this->signature;
    }
}