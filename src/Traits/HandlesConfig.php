<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Configuration\ComponentConfig;
use Cognesy\Instructor\Configuration\Configuration;

trait HandlesConfig
{
    protected Configuration $config;

    /**
     * Returns the current configuration
     */
    public function config() : Configuration {
        return $this->config;
    }

    /**
     * Overrides the default configuration
     */
    public function withConfig(array $config) : self {
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
