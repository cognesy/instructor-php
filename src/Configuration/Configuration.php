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
    use Traits\HasInstance;
    use Traits\HandlesConfig;
    use Traits\HandlesInstances;
    use HandlesEvents;
    use HandlesEventListeners;

    public function __construct() {
        $this->events = new EventDispatcher();
    }
}
