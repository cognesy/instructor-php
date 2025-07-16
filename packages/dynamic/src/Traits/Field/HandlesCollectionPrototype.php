<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\Field;

use Cognesy\Dynamic\Structure;

trait HandlesCollectionPrototype
{
    protected ?Structure $prototype;

    public function hasPrototype() : bool {
        return $this->prototype !== null;
    }

    public function prototype() : ?Structure {
        return $this->prototype;
    }

    public function clone() : self {
        $clone = clone $this;
        $clone->defaultValue = $this->defaultValue;
        $clone->value = null;
        $clone->required = $this->required;
        $clone->prototype = $this->prototype;
        return $clone;
    }
}