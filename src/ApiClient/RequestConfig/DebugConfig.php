<?php

namespace Cognesy\Instructor\ApiClient\RequestConfig;

class DebugConfig
{
    private bool $debug;
    private bool $stopOnDebug;
    private bool $forceDebug;

    public function __construct(
        bool $debug = false,
        bool $stopOnDebug = false,
        bool $forceDebug = false,
    ) {
        $this->debug = $debug;
        $this->stopOnDebug = $stopOnDebug;
        $this->forceDebug = $forceDebug;
    }

    public function withDebug(bool $debug = true) : static {
        $this->debug = $debug;
        return $this;
    }

    public function debug() : bool {
        return $this->debug || $this->forceDebug;
    }

    public function withStopOnDebug(bool $stopOnDebug = true) : static {
        $this->stopOnDebug = $stopOnDebug;
        return $this;
    }

    public function stopOnDebug() : bool {
        return $this->stopOnDebug;
    }
}