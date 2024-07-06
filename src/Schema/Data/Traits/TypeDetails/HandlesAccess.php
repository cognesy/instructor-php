<?php

namespace Cognesy\Instructor\Schema\Data\Traits\TypeDetails;

use Cognesy\Instructor\Schema\Data\TypeDetails;

trait HandlesAccess
{
    public function type() : string {
        return $this->type;
    }

    public function class() : ?string {
        return $this->class;
    }

    public function nestedType() : ?TypeDetails {
        return $this->nestedType;
    }

    public function enumType() : ?string {
        return $this->enumType;
    }

    public function enumValues() : ?array {
        return $this->enumValues;
    }

    public function docString() : string {
        return $this->docString;
    }

    public function __toString() : string {
        return $this->toString();
    }
}