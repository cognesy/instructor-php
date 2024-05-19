<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

trait HandlesEndpoint
{
    protected string $defaultEndpoint = '/chat/completions';

    public function resolveEndpoint() : string {
        return $this->endpoint ?: $this->defaultEndpoint;
    }
}