<?php

namespace Cognesy\Instructor\Evaluation\Parameter;

class Parameter {
    public mixed $value;
    public string $name;
    /** Provides context for the parameter */
    public string $roleDescription;
    public bool $requiresGradient = false; // requires grad
    /** @var Parameter[] */
    public array $predecessors = [];
    /** @var Parameter[] */
    public array $gradients = [];
    public array $gradientContexts = [];


    public function __construct(
        string $name,
        mixed $value,
        string $roleDescription,
        bool $requiresGradient = true,
        array $predecessors = [],
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->roleDescription = $roleDescription;
        $this->requiresGradient = $requiresGradient;
        $this->predecessors = $predecessors ?? [];
    }

    public function __toString() : string {
        return (string) $this->value;
    }
}
