<?php

namespace Cognesy\Instructor\ApiClient\Traits;

trait HandlesDefaultModel
{
    public string $defaultModel = '';

    public function defaultModel() : string {
        return $this->defaultModel;
    }

    protected function getModel(string $model) : string {
        return $model ?: $this->defaultModel();
    }
}