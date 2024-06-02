<?php

namespace Cognesy\Instructor\Extras\FunctionCall\Traits;

trait HandlesDeserialization
{
    public function fromJson(string $jsonData, string $toolName = null): static {
        $this->arguments = $this->arguments->fromJson($jsonData);
        return $this;
    }
}