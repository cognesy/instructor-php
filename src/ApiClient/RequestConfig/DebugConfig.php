<?php

namespace Cognesy\Instructor\ApiClient\RequestConfig;

class DebugConfig
{
    private bool $debug = false;
    private bool $stopOnDebug = false;

    public function __construct(
        bool $debug = false,
        bool $stopOnDebug = false
    ) {
        $this->withDebug($debug);
        $this->withStopOnDebug($stopOnDebug);
    }

    public function withDebug(bool $debug = true) : static {
        $this->debug = $debug;
        return $this;
    }

    public function debug() : bool {
        return $this->debug;
    }

    public function withStopOnDebug(bool $stopOnDebug = true) : static {
        $this->stopOnDebug = $stopOnDebug;
        return $this;
    }

    public function stopOnDebug() : bool {
        return $this->stopOnDebug;
    }
}