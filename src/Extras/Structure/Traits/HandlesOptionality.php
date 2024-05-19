<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

trait HandlesOptionality
{
    private bool $required = true;

    public function required(bool $isRequired = false) : self {
        $this->required = $isRequired;
        return $this;
    }

    public function optional(bool $isOptional = true) : self {
        $this->required = !$isOptional;
        return $this;
    }
}