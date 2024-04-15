<?php

namespace Cognesy\Instructor\Traits;

class HandlesCaching
{
    protected bool $cache = false;

    public function cache() : bool {
        return $this->cache;
    }

    public function withCache(bool $cache = true) : static {
        $this->cache = $cache;
        return $this;
    }
}