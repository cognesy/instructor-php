<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

trait HandlesStructureInfo
{
    protected string $name = '';
    protected string $description = '';

    public function withName(string $name) : self {
        $this->name = $name;
        return $this;
    }

    public function name() : string {
        return $this->name;
    }

    public function withDescription(string $description) : self {
        $this->description = $description;
        return $this;
    }

    public function description() : string {
        return $this->description;
    }
}