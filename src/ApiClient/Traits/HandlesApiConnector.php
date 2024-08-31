<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\LLMConnector;
use Cognesy\Instructor\ApiClient\RequestConfig\DebugConfig;

trait HandlesApiConnector
{
    protected LLMConnector $connector;

    public function withConnector(LLMConnector $connector) : static {
        $this->connector = $connector;
        return $this;
    }

    public function connector(DebugConfig $debugConfig = null) : LLMConnector {
        if ($debugConfig && $debugConfig->debug()) {
            $this->connector->debug(die: $debugConfig->stopOnDebug());
        }
        return $this->connector;
    }
}