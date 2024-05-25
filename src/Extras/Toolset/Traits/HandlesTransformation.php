<?php

namespace Cognesy\Instructor\Extras\Toolset\Traits;

trait HandlesTransformation
{
    public function transform(): mixed {
        return $this->call->transform();
    }
}