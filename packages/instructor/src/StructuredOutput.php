<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Data\StructuredOutputRequestInfo;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Features\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Features\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Features\Transformation\ResponseTransformer;
use Cognesy\Instructor\Features\Validation\ResponseValidator;
use Cognesy\Instructor\Features\Validation\Validators\SymfonyValidator;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Events\Traits\HandlesEvents;

/**
 * The StructuredOutput class manages the lifecycle and functionalities of StructuredOutput instance.
 *
 * It uses various traits including event management, environment settings, and request handling.
 *
 * @uses HandlesEvents
 * @uses Traits\HandlesInvocation
 * @uses Traits\HandlesOverrides
 * @uses Traits\HandlesPartialUpdates
 * @uses Traits\HandlesQueuedEvents
 * @uses Traits\HandlesRequest
 * @uses Traits\HandlesSequenceUpdates
 */
class StructuredOutput
{
    use HandlesEvents;

    use Traits\HandlesFluentMethods;
    use Traits\HandlesInvocation;
    use Traits\HandlesOverrides;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesQueuedEvents;
    use Traits\HandlesRequest;
    use Traits\HandlesSequenceUpdates;

    private LLM $llm;
    private StructuredOutputRequest $request;

    private ResponseDeserializer $responseDeserializer;
    private ResponseValidator $responseValidator;
    private ResponseTransformer $responseTransformer;

    private StructuredOutputConfig $config;

    /**
     * @param LLM|null $llm An optional LLM object instance for LLM connection.
     * @param EventDispatcher|null $events An optional EventDispatcher instance for managing events.
     * @return void
     */
    public function __construct(
        ?LLM $llm = null,
        ?EventDispatcher $events = null,
        StructuredOutputConfig $config = null,
    ) {
        // load config
        $this->config = $config ?? StructuredOutputConfig::load();

        // queue 'STARTED' event, to dispatch it after user is ready to handle it
        $this->queueEvent(new InstructorStarted());

        // main event dispatcher
        $this->events = $events ?? new EventDispatcher('instructor');

        $this->responseDeserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
        $this->responseValidator = new ResponseValidator($this->events, [SymfonyValidator::class]);
        $this->responseTransformer = new ResponseTransformer($this->events, []);

        $this->llm = $llm ?? new LLM(events: $this->events);
        $this->requestInfo = new StructuredOutputRequestInfo();

        // queue 'READY' event
        $this->queueEvent(new InstructorReady());
    }

    /**
     * Enables or disables debug mode for the current instance.
     *
     * @param bool $debug Optional. If true, enables debug mode; otherwise, disables it. Defaults to true.
     * @return static The current instance with the updated debug state.
     */
    public function withDebug(bool $debug = true) : static {
        $this->llm->withDebug($debug);
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config) : static {
        $this->config = $config;
        return $this;
    }
}