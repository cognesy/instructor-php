<?php

namespace Cognesy\Instructor\Traits\Instructor;

trait HandlesCaching
{
    public function cache() : bool {
        return $this->apiRequestConfig->isCached();
    }

    public function withCache(bool $cache = true) : static {
        $this->apiRequestConfig->withCache($cache);
        return $this;
    }
}