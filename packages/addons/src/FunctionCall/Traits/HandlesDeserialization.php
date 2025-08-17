<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall\Traits;

trait HandlesDeserialization
{
    public function fromJson(string $jsonData, ?string $toolName = null): static {
        $this->arguments = $this->arguments->fromJson($jsonData);
        $this->name = $toolName ?? $this->name;
        return $this;
    }
}
