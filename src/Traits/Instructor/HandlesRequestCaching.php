<?php

namespace Cognesy\Instructor\Traits\Instructor;

trait HandlesRequestCaching
{
    public function isRequestCached() : bool {
        return $this->apiRequestConfig->isCached();
    }

    public function withRequestCache(bool $cache = true) : static {
        $this->apiRequestConfig->withCache($cache);
        return $this;
    }
}