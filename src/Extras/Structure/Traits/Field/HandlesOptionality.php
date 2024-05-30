<?php
namespace Cognesy\Instructor\Extras\Structure\Traits\Field;

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

    public function isRequired() : bool {
        return $this->required;
    }

    public function isOptional() : bool {
        return !$this->required;
    }
}