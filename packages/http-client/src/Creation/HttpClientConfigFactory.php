<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Http\Config\HttpClientConfig;

class HttpClientConfigFactory
{
    public function __construct() {}

    public function default(): HttpClientConfig {
        return new HttpClientConfig();
    }
}
