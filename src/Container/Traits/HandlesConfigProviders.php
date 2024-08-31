<?php
namespace Cognesy\Instructor\Container\Traits;

use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Container\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Container\Contracts\CanProvideConfiguration;

trait HandlesConfigProviders
{
    public function fromConfigProvider(CanProvideConfiguration|CanAddConfiguration $configProvider) : static {
        if ($configProvider instanceof CanAddConfiguration) {
            $configProvider->addConfiguration($this);
        } elseif ($configProvider instanceof CanProvideConfiguration) {
            $config = $configProvider->toConfiguration();
            $this->merge($config);
        }
        return $this;
    }

    public function fromConfigProviders(array $configProviders) : static {
        foreach ($configProviders as $configProvider) {
            $this->fromConfigProvider($configProvider);
        }
        return $this;
    }

    public function merge(Container $config) : static {
        $this->config = array_merge($this->config, $config->config);
        return $this;
    }
}
