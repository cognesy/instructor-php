<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

trait HandlesStructureInfo
{
    protected string $name = '';
    protected string $description = '';
    protected string $instructions = '';

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

    public function withInstructions(string $instructions) : self {
        $this->instructions = $instructions;
        return $this;
    }

    public function instructions() : string {
        return $this->instructions;
    }

    public function info() : string {
        return implode('; ', array_filter([
            $this->description(),
            $this->instructions(),
        ]));
    }
}