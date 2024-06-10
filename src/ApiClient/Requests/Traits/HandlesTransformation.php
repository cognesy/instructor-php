<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

trait HandlesTransformation
{
    public function toArray() : array {
        return [
            'class' => static::class,
            'endpoint' => $this->resolveEndpoint(),
            'method' => $this->method,
            'body' => $this->defaultBody(),
        ];
    }
}