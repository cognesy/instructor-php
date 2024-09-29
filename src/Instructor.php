<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\Response\ResponseGenerator;
use Cognesy\Instructor\Core\StreamResponse\PartialsGenerator;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Extras\Debug\Debug;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    use Events\Traits\HandlesEvents;
    use Events\Traits\HandlesEventListeners;

    use Traits\HandlesEnv;

    use Traits\Instructor\HandlesErrors;
    use Traits\Instructor\HandlesInvocation;
    use Traits\Instructor\HandlesOverrides;
    use Traits\Instructor\HandlesPartialUpdates;
    use Traits\Instructor\HandlesQueuedEvents;
    use Traits\Instructor\HandlesRequest;
    use Traits\Instructor\HandlesSequenceUpdates;

    //private LoggerInterface $logger;
    //private EventLogger $eventLogger;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        // queue 'STARTED' event, to dispatch it after user is ready to handle it
        $this->queueEvent(new InstructorStarted());

        // main event dispatcher
        $this->events = $events ?? new EventDispatcher('instructor');

        // wire up logging
        //$this->logger = $this->config->get(LoggerInterface::class);
        //$this->eventLogger = $this->config->get(EventLogger::class);
        //$this->events->wiretap($this->eventLogger->eventListener(...));

        // get other components from configuration

        $this->responseDeserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
        $this->responseValidator = new ResponseValidator($this->events, [SymfonyValidator::class]);
        $this->responseTransformer = new ResponseTransformer($this->events, []);

        $this->requestHandler = new RequestHandler(
            new ResponseGenerator(
                $this->responseDeserializer,
                $this->responseValidator,
                $this->responseTransformer,
                $this->events,
            ),
            new PartialsGenerator(
                $this->responseDeserializer,
                $this->responseTransformer,
                $this->events,
            ),
            $this->events,
        );

        // queue 'READY' event
        $this->queueEvent(new InstructorReady());
    }

    public function withDebug(bool $debug = true) : static {
        Debug::setEnabled($debug); // TODO: fix me - debug should not be global, should be request specific
        return $this;
    }
}
