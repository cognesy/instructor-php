<?php

namespace Cognesy\Instructor\Services;

use Cognesy\Instructor\Configuration\Configuration;

class ConfigurationService
{
    public function load(string $path) : Configuration {
        return new Configuration;
    }

    public function set(string $key, mixed $value) : void {
    }

    public function get(string $key) : mixed {
        return '';
    }
}
