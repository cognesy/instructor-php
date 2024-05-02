<?php

namespace Cognesy\Instructor\Services;

use Cognesy\Instructor\Configuration\Configuration;

// TODO: this is part of refactoring in progress - currently not used

class ConfigurationService
{
    private Configuration $config;

    public function load(string $path) : Configuration {
        $this->config = new Configuration;
    }

    public function set(string $key, mixed $value) : void {

    }

    public function get(string $key) : mixed {
        return '';
    }
}
