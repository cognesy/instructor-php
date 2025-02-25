<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Field;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;

trait HandlesCollectionPrototype
{
    protected ?Structure $prototype;

    public function hasPrototype() : bool {
        return $this->prototype !== null;
    }

    public function prototype() : ?Structure {
        return $this->prototype;
    }

    public function clone() : Field {
        $clone = clone $this;
        $clone->defaultValue = $this->defaultValue;
        $clone->value = null;
        $clone->required = $this->required;
        $clone->prototype = $this->prototype;
        return $clone;
    }
}