<?php
namespace Cognesy\Instructor\Container;

use Cognesy\Instructor\Events\Container\ContainerInitiated;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;
use Psr\Container\ContainerInterface;

/**
 * Manages configuration and wiring of the components
 */
class Container implements ContainerInterface
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
        $this->events = $events ?? new EventDispatcher('configuration');
        $this->events->dispatch(new ContainerInitiated());
    }
}
