<?php

namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesInput
{
    private string|array|object $input = '';

    public function input(): array {
        return $this->input;
    }
}
