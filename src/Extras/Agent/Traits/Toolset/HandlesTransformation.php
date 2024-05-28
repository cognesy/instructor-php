<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Toolset;

trait HandlesTransformation
{
    public function transform(): mixed {
        return $this->call->transform();
    }
}