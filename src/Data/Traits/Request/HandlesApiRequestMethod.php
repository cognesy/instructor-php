<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Saloon\Enums\Method;

trait HandlesApiRequestMethod
{
    private Method $method = Method::POST;

    public function method() : Method {
        return $this->method;
    }
}