<?php

namespace Cognesy\Instructor\Extras\Toolset\Traits;

trait HandlesDeserialization
{
    public function fromJson(string $jsonData, string $toolName = null): static {
        if ($toolName === null) {
            throw new \Exception('Tool name is required');
        }
        if (!$this->hasTool($toolName)) {
            throw new \Exception("Tool not found: $toolName");
        }
        $this->call = $this->getTool($toolName)->getCall()->fromJson($jsonData, $toolName);
        return $this;
    }
}