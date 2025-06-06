<?php

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<HttpClientConfig>
 */
interface CanProvideHttpClientConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = '') : HttpClientConfig;
}