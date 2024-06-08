<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;

trait HandlesApiRequestConfig
{
    private ApiRequestConfig $requestConfig;

    public function requestConfig() : ApiRequestConfig {
        return $this->requestConfig;
    }
}