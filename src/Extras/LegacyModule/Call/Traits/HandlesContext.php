<?php

namespace Cognesy\Instructor\Extras\Module\Call\Traits;

trait HandlesContext
{
    protected array $context = [];

    public function context(): array {
        return $this->context;
    }

    public function withContext(array $context): static {
        $this->context = $context;
        return $this;
    }
}