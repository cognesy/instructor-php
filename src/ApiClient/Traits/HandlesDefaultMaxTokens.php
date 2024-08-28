<?php

namespace Cognesy\Instructor\ApiClient\Traits;

trait HandlesDefaultMaxTokens
{
    public int $defaultMaxTokens = 512;

    public function defaultMaxTokens() : int {
        return $this->defaultMaxTokens;
    }

    public function withMaxTokens(int $maxTokens) : static {
        $this->defaultMaxTokens = $maxTokens;
        return $this;
    }
}
