<?php

namespace Cognesy\Instructor\Container\Traits;

use Exception;

trait HandlesConfigInclude
{
    protected string $configPath = __DIR__ . '/../../../config/';

    public function setConfigPath(string $configPath): void {
        $this->configPath = $configPath;
    }

    public function includeFile(string $configFile) : static {
        $configFile = $this->configPath . $configFile;
        $configCall = require $configFile;
        if (!is_callable($configCall)) {
            throw new Exception("Config file $configFile does not return a callable");
        }
        $configCall($this);
        return $this;
    }

    public function includeFiles(array $configFiles) : static {
        foreach ($configFiles as $configFile) {
            $this->includeFile($configFile);
        }
        return $this;
    }
}