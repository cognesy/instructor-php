<?php

namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesInput
{
    private string|array|object $input = '';

    public function input(): string|array|object {
        return $this->input;
    }
}
