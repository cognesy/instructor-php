<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\ApiConnector;

trait HandlesApiConnector
{
    protected ApiConnector $connector;

    public function withConnector(ApiConnector $connector) : static {
        $this->connector = $connector;
        return $this;
    }

    public function connector(bool $debug = false) : ApiConnector {
        if ($debug) {
            $this->connector->debug();
        }
        return $this->connector;
    }
}