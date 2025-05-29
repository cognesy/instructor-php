<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Data\StructuredOutputRequestBuilder;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputReady;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Polyglot\LLM\LLMFactory;
use Cognesy\Utils\Events\Contracts\EventListenerInterface;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
use Cognesy\Utils\Events\Traits\HandlesEventListening;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The StructuredOutput is facade for handling structured output requests and responses.
 */
class StructuredOutput
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    use Traits\HandlesFluentMethods;
    use Traits\HandlesInitMethods;
    use Traits\HandlesShortcuts;
    use Traits\HandlesInvocation;
    use Traits\HandlesOverrides;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesQueuedEvents;
    use Traits\HandlesSequenceUpdates;

    private StructuredOutputRequest $request;
    private StructuredOutputRequestBuilder $requestBuilder;

    private ResponseDeserializer $responseDeserializer;
    private ResponseValidator $responseValidator;
    private ResponseTransformer $responseTransformer;

    private StructuredOutputConfig $config;

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    /**
     * @param LLM|null $llm An optional LLM object instance for LLM connection.
     * @param StructuredOutputConfig|null $config An optional StructuredOutputConfig instance for configuration.
     * @param EventDispatcherInterface|null $events An optional EventDispatcherInterface instance for managing events.
     * @param EventListenerInterface|null $listener An optional EventListenerInterface instance for listening to events.
     */
    public function __construct(
        ?LLM $llm = null,
        ?StructuredOutputConfig $config = null,
        ?EventDispatcherInterface $events = null,
        ?EventListenerInterface $listener = null,
    ) {
        // load config
        $this->config = $config ?? StructuredOutputConfig::load();

        // queue 'STARTED' event, to dispatch it after user is ready to handle it
        $this->queueEvent(new StructuredOutputStarted());

        // main event dispatcher
        if (is_null($events) || is_null($listener)) {
            $defaultEventProcessor = new EventDispatcher('instructor');
        }
        $this->events = $events ?? $defaultEventProcessor;
        $this->listener = $listener ?? $defaultEventProcessor;

        $this->responseDeserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
        $this->responseValidator = new ResponseValidator($this->events, [SymfonyValidator::class]);
        $this->responseTransformer = new ResponseTransformer($this->events, []);

        $this->requestBuilder = new StructuredOutputRequestBuilder(
            config: $this->config,
            events: $this->events,
            listener: $this->listener,
        );

        $this->llmFactory = new LLMFactory();

        // queue 'READY' event
        $this->queueEvent(new StructuredOutputReady());
    }
}