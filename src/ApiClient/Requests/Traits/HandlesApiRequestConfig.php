<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;

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
}