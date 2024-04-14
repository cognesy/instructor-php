<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\Traits\HandlesDebug;

trait HandlesApiConnector
{
    use HandlesDebug;

    protected ApiConnector $connector;

    public function withConnector(ApiConnector $connector) : static {
        $this->connector = $connector;
        return $this;
    }

    public function connector() : ApiConnector {
        if ($this->debug()) {
            return $this->connector->debug();
        }
        return $this->connector;
    }
}