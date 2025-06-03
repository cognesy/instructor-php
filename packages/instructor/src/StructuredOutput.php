<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanProvideStructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Polyglot\LLM\LLMProvider;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\EventHandlerFactory;
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
    use Traits\HandlesQueuedEvents;

    use Traits\HandlesInitMethods;
    use Traits\HandlesInvocation;
    use Traits\HandlesShortcuts;
    use Traits\HandlesRequestBuilder;

    use Traits\HandlesOverrides;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesSequenceUpdates;

    private ResponseDeserializer $responseDeserializer;
    private ResponseValidator $responseValidator;
    private ResponseTransformer $responseTransformer;
    private CanProvideStructuredOutputConfig $configProvider;

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    /**
     * @param LLMProvider|null $llm An optional LLM object instance for LLM connection.
     * @param StructuredOutputConfig|null $config An optional StructuredOutputConfig instance for configuration.
     * @param EventDispatcherInterface|null $events An optional EventDispatcherInterface instance for managing events.
     * @param CanRegisterEventListeners|null $listener An optional EventListenerInterface instance for listening to events.
     */
    public function __construct(
        ?EventDispatcherInterface  $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideStructuredOutputConfig $configProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();

        $this->responseDeserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
        $this->responseValidator = new ResponseValidator($this->events, [SymfonyValidator::class]);
        $this->responseTransformer = new ResponseTransformer($this->events, []);

        $this->configProvider = $configProvider ?? new SettingsStructuredOutputConfigProvider();
        $this->config = $this->configProvider->getConfig();
        $this->cachedContext = new Data\CachedContext();
        $this->llm = new LLMProvider(
            events: $this->events,
            listener: $this->listener,
        );
    }
}