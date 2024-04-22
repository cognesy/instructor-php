<?php
namespace Cognesy\Instructor\Configuration;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;

/**
 * Manages configuration and wiring of the components
 */
class Configuration
{
    use Traits\HasConfigurationInstance;
    use Traits\HandlesConfig;
    use Traits\HandlesComponentInstances;
    use HandlesEvents;
    use HandlesEventListeners;

    public function __construct() {
        $this->events = new EventDispatcher();
    }
}
