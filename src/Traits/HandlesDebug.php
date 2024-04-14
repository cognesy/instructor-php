<?php

namespace Cognesy\Instructor\Traits;

trait HandlesDebug
{
    protected bool $debug = false;

    public function debug() : bool {
        return $this->debug;
    }

    public function withDebug(bool $debug = true) : static {
        $this->debug = $debug;
        return $this;
    }
}
