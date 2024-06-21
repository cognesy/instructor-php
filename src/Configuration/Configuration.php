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
    use Traits\HandlesComponentWiring;
    use Traits\HandlesConfigSetup;
    use Traits\HandlesConfigInclude;
    use Traits\HandlesConfigProviders;
    use Traits\HasConfigurationInstance;
    use Traits\PreventsCycles;

    public function __construct(EventDispatcher $events = null) {
        $this->events = new EventDispatcher('configuration');
        $this->events->dispatch(new ConfigurationInitiated());
    }
}
