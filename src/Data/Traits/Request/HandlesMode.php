<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Enums\Mode;

trait HandlesMode
{
    private Mode $mode;

    public function mode() : Mode {
        return $this->mode;
    }
}