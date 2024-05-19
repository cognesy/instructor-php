<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

trait HandlesDebug
{
    protected bool $debug = false;

    public function isDebug(): bool {
        return $this->debug;
    }
}