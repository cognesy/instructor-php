<?php

namespace Cognesy\Instructor\ApiClient\Traits;

trait HandlesDefaultMaxTokens
{
    public int $defaultMaxTokens = 512;

    public function defaultMaxTokens() : int {
        return $this->defaultMaxTokens;
    }
}