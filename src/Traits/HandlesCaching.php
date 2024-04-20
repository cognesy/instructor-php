<?php

namespace Cognesy\Instructor\Traits;

trait HandlesCaching
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