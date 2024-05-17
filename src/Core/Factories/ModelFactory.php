<?php

namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\ApiClient\ModelParams;

class ModelFactory
{
    public function __construct(
        private array $models = [],
        private bool $allowUnknownModels = true,
    ) {}

    public function has(string $name) : bool {
        return isset($this->models[$name]);
    }

    public function get(string $name) : ModelParams {
        if ($this->has($name)) {
            return ($this->models[$name])();
        }
        if (!$this->allowUnknownModels) {
            throw new \Exception("Model not found: $name");
        }
        return new ModelParams(name: $name);
    }
}
