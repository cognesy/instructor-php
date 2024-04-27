<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

trait HandlesDescription
{
    protected string $name = '';
    protected string $description = '';

    public function withName(string $name) : self {
        $this->name = $name;
        return $this;
    }

    public function withDescription(string $description) : self {
        $this->description = $description;
        return $this;
    }

    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->description;
    }
}