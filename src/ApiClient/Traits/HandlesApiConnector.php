<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\RequestConfig\DebugConfig;

trait HandlesApiConnector
{
    protected ApiConnector $connector;

    public function withConnector(ApiConnector $connector) : static {
        $this->connector = $connector;
        return $this;
    }

    public function connector(DebugConfig $debugConfig = null) : ApiConnector {
        if ($debugConfig && $debugConfig->debug()) {
            $this->connector->debug(die: $debugConfig->stopOnDebug());
        }
        return $this->connector;
    }
}