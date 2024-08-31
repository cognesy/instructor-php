<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Container\ComponentConfig;
use Cognesy\Instructor\Container\Container;

trait HandlesConfig
{
    protected Container $config;

    protected function setConfig(Container $config) : void {
        $this->config = $config;
    }

    /**
     * Returns the current configuration
     */
    public function config() : Container {
        return $this->config;
    }

    /**
     * Overrides the default configuration
     */
    public function withConfig(array $config) : static {
        $this->config->override($config);
        return $this;
    }

    /**
     * Returns the current configuration
     */
    public function getComponent(string $component) : ?object {
        if (!$this->config->has($component)) {
            return null;
        }
        return $this->config->get($component);
    }

    /**
     * Returns the current configuration
     */
    public function getComponentConfig(string $component) : ?ComponentConfig {
        if (!$this->config->has($component)) {
            return null;
        }
        return $this->config->getConfig($component);
    }
}
