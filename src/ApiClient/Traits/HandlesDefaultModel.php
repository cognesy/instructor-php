<?php

namespace Cognesy\Instructor\ApiClient\Traits;

trait HandlesDefaultModel
{
    public string $defaultModel = '';

    public function defaultModel() : string {
        return $this->defaultModel;
    }

    public function withModel(string $model) : static {
        $this->defaultModel = $model;
        return $this;
    }
}
