<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Structure;

trait HandlesFieldDescription
{
    private string $name;
    private string $description;
    private bool $required = true;

    public function withName(string $name) : self {
        $this->name = $name;
        if ($this->typeDetails->class === Structure::class) {
            $this->value->withName($name);
        }
        return $this;
    }

    public function withDescription(string $description) : self {
        $this->description = $description;
        return $this;
    }

    public function name() : string {
        return $this->name ?? '';
    }

    public function description() : string {
        return $this->description ?? '';
    }

    public function required(bool $isRequired = false) : self {
        $this->required = $isRequired;
        return $this;
    }

    public function optional(bool $isOptional = true) : self {
        $this->required = !$isOptional;
        return $this;
    }
}