<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

interface HasVariables
{
    public function variables(): array;
    public function hasVariable(string $name): bool;
    public function variable(string $name, mixed $default = null): mixed;
    public function withVariable(string $name, mixed $value): static;
}