<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall\Traits;

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