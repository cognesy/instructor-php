<?php

namespace Cognesy\Instructor\ApiClient\Traits;

trait HandlesDefaultModel
{
    public string $defaultModel = '';

    public function defaultModel() : string {
        return $this->defaultModel;
    }
}