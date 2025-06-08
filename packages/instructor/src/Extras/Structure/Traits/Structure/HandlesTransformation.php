<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Structure;

use Cognesy\Instructor\Extras\Structure\Structure;

trait HandlesTransformation
{
    public function transform() : mixed {
        return $this;
    }

    public function clone() : self {
        $new = new Structure();
        $new->name = $this->name;
        $new->description = $this->description;
        $new->validator = $this->validator;
        $new->fields = [];
        foreach ($this->fields as $name => $field) {
            $new->fields[$name] = $field->clone();
        }
        return $new;
    }
}