<?php

namespace Cognesy\Instructor;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Events\EventHandlerFactory;
use Cognesy\Events\Traits\HandlesEventDispatching;
use Cognesy\Events\Traits\HandlesEventListening;
use Cognesy\Instructor\Core\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Core\StructuredOutputRequestBuilder;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Polyglot\LLM\LLMProvider;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The StructuredOutput is facade for handling structured output requests and responses.
 */
class StructuredOutput
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    use Traits\HandlesLLMProvider;
    use Traits\HandlesInvocation;
    use Traits\HandlesShortcuts;
    use Traits\HandlesRequestBuilder;
    use Traits\HandlesConfigBuilder;

    use Traits\HandlesOverrides;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesSequenceUpdates;

    private ResponseDeserializer $responseDeserializer;
    private ResponseValidator $responseValidator;
    private ResponseTransformer $responseTransformer;

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    public function __construct(
        ?EventDispatcherInterface  $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideConfig          $configProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();

        $this->responseDeserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
        $this->responseValidator = new ResponseValidator($this->events, [SymfonyValidator::class]);
        $this->responseTransformer = new ResponseTransformer($this->events, []);

        $this->configBuilder = new StructuredOutputConfigBuilder(configProvider: $configProvider);
        $this->requestBuilder = new StructuredOutputRequestBuilder();

        $this->llmProvider = LLMProvider::new(
            events: $this->events,
            listener: $this->listener,
            configProvider: $configProvider,
        );
    }
}