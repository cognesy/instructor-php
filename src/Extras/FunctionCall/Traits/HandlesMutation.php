<?php

namespace Cognesy\Instructor\Extras\FunctionCall\Traits;

trait HandlesMutation
{
    public function withName(string $name): static {
        $this->name = $name;
        return $this;
    }

    public function withDescription(string $description): static {
        $this->description = $description;
        return $this;
    }
}