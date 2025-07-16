<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\Structure;

use Cognesy\Dynamic\Structure;

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