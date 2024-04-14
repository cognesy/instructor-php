<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\ApiConnector;

trait HandlesApiConnector
{
    protected ApiConnector $connector;

    public function connector() : ApiConnector {
        return $this->connector;
    }
}