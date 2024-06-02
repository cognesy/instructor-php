<?php

namespace Cognesy\Instructor\Extras\Module\TaskData\Traits\TaskData;

use Cognesy\Instructor\Extras\Module\Signature\Signature;

trait HandlesSignature
{
    public function signature(): Signature {
        return $this->signature;
    }
}