<?php

namespace Cognesy\Instructor\Extras\Agent;

class ExecutionContext
{
    private array $data;

    public function __construct(
        array $data = [],
    ) {
        $this->data = $data;
    }

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    public function remove(string $key): void {
        unset($this->data[$key]);
    }

    public function variables(): array {
        return $this->data;
    }

    public function withVariables(array $variables): self {
        $this->data = array_merge($this->data, $variables);
        return $this;
    }

    public function withVariable(string $key, mixed $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    public function toArray(): array {
        return $this->data;
    }

    public function reset(): void {
        $this->data = [];
    }
}