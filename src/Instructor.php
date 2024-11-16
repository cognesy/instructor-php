<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Features\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Features\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Features\Transformation\ResponseTransformer;
use Cognesy\Instructor\Features\Validation\ResponseValidator;
use Cognesy\Instructor\Features\Validation\Validators\SymfonyValidator;
use Cognesy\Instructor\Utils\Debug\Debug;

/**
 * The Instructor class manages the lifecycle and functionalities of Instructor instance.
 *
 * It uses various traits including event management, environment settings, and request handling.
 *
 * @uses Events\Traits\HandlesEvents
 * @uses Events\Traits\HandlesEventListeners
 * @uses Traits\HandlesInvocation
 * @uses Traits\HandlesOverrides
 * @uses Traits\HandlesPartialUpdates
 * @uses Traits\HandlesQueuedEvents
 * @uses Traits\HandlesRequest
 * @uses Traits\HandlesSequenceUpdates
 */
class Instructor {
    use Events\Traits\HandlesEvents;
    use Events\Traits\HandlesEventListeners;

    use Traits\HandlesInvocation;
    use Traits\HandlesOverrides;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesQueuedEvents;
    use Traits\HandlesRequest;
    use Traits\HandlesSequenceUpdates;

    /**
     * @param EventDispatcher|null $events An optional EventDispatcher instance for managing events.
     * @return void
     */
    public function __construct(
        EventDispatcher $events = null,
    ) {
        // queue 'STARTED' event, to dispatch it after user is ready to handle it
        $this->queueEvent(new InstructorStarted());

        // main event dispatcher
        $this->events = $events ?? new EventDispatcher('instructor');

        $this->responseDeserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
        $this->responseValidator = new ResponseValidator($this->events, [SymfonyValidator::class]);
        $this->responseTransformer = new ResponseTransformer($this->events, []);

        // queue 'READY' event
        $this->queueEvent(new InstructorReady());
    }

    /**
     * Initializes an Instructor instance with a specified connection.
     *
     * @param string $connection The connection string to be used.
     * @return Instructor An instance of Instructor with the specified connection.
     */
    public static function using(string $connection) : Instructor {
        return (new static)->withConnection($connection);
    }

    /**
     * Enables or disables debug mode for the current instance.
     *
     * @param bool $debug Optional. If true, enables debug mode; otherwise, disables it. Defaults to true.
     * @return static The current instance with the updated debug state.
     */
    public function withDebug(bool $debug = true) : static {
        Debug::setEnabled($debug); // TODO: fix me - debug should not be global, should be request specific
        return $this;
    }
}
