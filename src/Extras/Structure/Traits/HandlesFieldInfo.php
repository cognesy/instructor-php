<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Structure;

trait HandlesFieldInfo
{
    private string $name = '';
    private string $description = '';
    private string $instructions = '';

    public function withName(string $name) : self {
        $this->name = $name;
        if ($this->typeDetails->class === Structure::class) {
            $this->value->withName($name);
        }
        return $this;
    }

    public function name() : string {
        return $this->name ?? '';
    }

    public function withDescription(string $description) : self {
        $this->description = $description;
        if ($this->typeDetails->class === Structure::class) {
            $this->value->withDescription($description);
        }
        return $this;
    }

    public function description() : string {
        return $this->description ?? '';
    }

    public function withInstructions(string $instructions) : self {
        $this->instructions = $instructions;
        if ($this->typeDetails->class === Structure::class) {
            $this->value->withInstructions($instructions);
        }
        return $this;
    }

    public function instructions() : string {
        return $this->instructions ?? '';
    }

    public function info() : string {
        return implode('; ', array_filter([
            $this->description(),
            $this->instructions(),
        ]));
    }
}