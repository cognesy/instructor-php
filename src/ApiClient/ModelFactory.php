<?php

namespace Cognesy\Instructor\ApiClient;

class ModelFactory
{
    public function __construct(
        private array $models = [],
    ) {}

    public function has(string $name) : bool {
        return isset($this->models[$name]);
    }

    public function get(string $name) : ModelParams {
        return ($this->models[$name])() ?? (new ModelParams(name: $name));
    }
}
