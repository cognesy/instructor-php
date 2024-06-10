<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Exception;

trait HandlesApiRequestConfig
{
    protected ApiRequestConfig $requestConfig;

    public function withRequestConfig(ApiRequestConfig $requestConfig) : static {
        $this->requestConfig = $requestConfig;
        return $this;
    }

    public function requestConfig() : ApiRequestConfig {
        return $this->requestConfig;
    }

    protected function applyRequestConfig() : void {
        if (!is_null($this->requestConfig)) {
            $this->cachingEnabled = $this->requestConfig->cacheConfig()->isEnabled();
            if ($this->cachingEnabled & $this->isStreamed()) {
                throw new Exception('Instructor does not support caching with streamed requests');
            }
        }
    }
}
