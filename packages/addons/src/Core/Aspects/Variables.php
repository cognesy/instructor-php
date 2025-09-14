<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Aspects;

use Cognesy\Addons\Core\StateContracts\HasVariables;

class Variables implements HasVariables
{
    private array $variables = [];

    public function __construct(
        array $variables = [],
    ) {}

    public function variables(): array {
        return $this->variables;
    }

    public function hasVariable(string $name): bool {
        return array_key_exists($name, $this->variables);
    }

    public function variable(string $name, mixed $default = null): mixed {
        return $this->variables[$name] ?? $default;
    }

    public function withVariable(string $name, mixed $value): static {
        $newVariables = $this->variables;
        $newVariables[$name] = $value;
        return new static($newVariables);
    }
}