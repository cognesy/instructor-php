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
        // Only null the value if it's not a Structure definition
        // Clone the Structure to avoid shared state between collection items
        $clone->value = (isset($this->value) && $this->value instanceof Structure) ? $this->value->clone() : null;
        $clone->required = $this->required;
        $clone->prototype = $this->prototype;
        return $clone;
    }
}