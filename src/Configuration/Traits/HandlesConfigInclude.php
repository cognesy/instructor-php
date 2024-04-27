<?php

namespace Cognesy\Instructor\Configuration\Traits;

trait HandlesConfigInclude
{
    protected string $configPath = __DIR__ . '/../../../config/';

    public function setConfigPath(string $configPath): void {
        $this->configPath = $configPath;
    }

    public function include(string $configFile) : void {
        $configFile = $this->configPath . $configFile;
        $configCall = require $configFile;
        if (is_callable($configCall)) {
            $configCall($this);
        }
    }
}