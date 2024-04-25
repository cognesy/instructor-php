<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;

trait HandlesApiRequestContext
{
    protected ApiRequestContext $context;

    public function withContext(ApiRequestContext $context) : static {
        $this->context = $context;
        return $this;
    }

    public function context() : ApiRequestContext {
        return $this->context;
    }
}