<?php
namespace Cognesy\Instructor\Configuration;

use Cognesy\Instructor\Events\Configuration\ConfigurationInitiated;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;
use Psr\Container\ContainerInterface;

/**
 * Manages configuration and wiring of the components
 */
class Configuration implements ContainerInterface
{
    use HandlesEvents;
    use HandlesEventListeners;

    use Traits\HandlesComponentInstances;
    use Traits\HandlesConfig;
    use Traits\HandlesConfigInclude;
    use Traits\HasConfigurationInstance;

    public function __construct(EventDispatcher $events = null) {
        $this->events = $events ?? new EventDispatcher('configuration');
        $this->events->dispatch(new ConfigurationInitiated());
    }

    public function canOverride(string $componentName): bool {
        return match(false) {
            is_null($this->getConfig($componentName)) => $this->allowOverride,
            default => true,
        };
    }
}
