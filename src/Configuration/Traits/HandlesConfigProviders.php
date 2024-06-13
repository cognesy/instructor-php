<?php
namespace Cognesy\Instructor\Configuration\Traits;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Configuration\Contracts\CanProvideConfiguration;

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

    public function merge(Configuration $config) : static {
        $this->config = array_merge($this->config, $config->config);
        return $this;
    }
}
