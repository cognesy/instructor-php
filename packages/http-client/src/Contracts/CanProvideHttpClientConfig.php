<?php

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpClientConfig;

interface CanProvideHttpClientConfig
{
    public function getConfig(string $preset = '') : HttpClientConfig;
}