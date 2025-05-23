<?php

namespace Cognesy\Instructor;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
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
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Utils\Events\Contracts\EventListenerInterface;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
use Cognesy\Utils\Events\Traits\HandlesEventListening;
use JetBrains\PhpStorm\Deprecated;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The StructuredOutput class manages the lifecycle and functionalities of StructuredOutput instance.
 *
 * It uses various traits including event management, environment settings, and request handling.
 */
class StructuredOutput
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    use Traits\HandlesFluentMethods;
    use Traits\HandlesInvocation;
    use Traits\HandlesOverrides;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesQueuedEvents;
    use Traits\HandlesSequenceUpdates;

    private LLM $llm;
    private StructuredOutputRequest $request;
    private StructuredOutputRequestInfo $requestInfo;

    private ResponseDeserializer $responseDeserializer;
    private ResponseValidator $responseValidator;
    private ResponseTransformer $responseTransformer;

    private StructuredOutputConfig $config;

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    /**
     * @param LLM|null $llm An optional LLM object instance for LLM connection.
     * @param EventDispatcherInterface|null $events An optional EventDispatcherInterface instance for managing events.
     * @return void
     */
    public function __construct(
        ?LLM $llm = null,
        StructuredOutputConfig $config = null,
        ?EventDispatcherInterface $events = null,
        ?EventListenerInterface $listener = null,
    ) {
        // load config
        $this->config = $config ?? StructuredOutputConfig::load();

        // queue 'STARTED' event, to dispatch it after user is ready to handle it
        $this->queueEvent(new InstructorStarted());

        // main event dispatcher
        if (is_null($events) || is_null($listener)) {
            $defaultEventProcessor = new EventDispatcher('instructor');
        }
        $this->events = $events ?? $defaultEventProcessor;
        $this->listener = $listener ?? $defaultEventProcessor;

        $this->responseDeserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
        $this->responseValidator = new ResponseValidator($this->events, [SymfonyValidator::class]);
        $this->responseTransformer = new ResponseTransformer($this->events, []);

        $this->llm = $llm ?? new LLM(events: $this->events);
        $this->requestInfo = new StructuredOutputRequestInfo();

        // queue 'READY' event
        $this->queueEvent(new InstructorReady());
    }

    /**
     * Initializes a StructuredOutput instance with a specified DSN.
     *
     * @param string $dsn The DSN string to be used.
     * @return StructuredOutput An instance of StructuredOutput with the specified DSN.
     */
    public static function fromDSN(string $dsn) : static {
        return (new StructuredOutput)->withDSN($dsn);
    }

    // MUTATORS ///////////////////////////////////////////////////////////

    public function withConfig(StructuredOutputConfig $config) : static {
        $this->config = $config;
        return $this;
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

    public function withDSN(string $dsn) : static {
        $llm = LLM::fromDSN($dsn);
        $this->llm = $llm;
        return $this;
    }

    public function withLLM(LLM $llm) : static {
        $this->llm = $llm;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config) : static {
        $this->llm->withConfig($config);
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : static {
        $this->llm->withDriver($driver);
        return $this;
    }

    public function withHttpClient(CanHandleHttpRequest $httpClient) : static {
        $this->llm->withHttpClient($httpClient);
        return $this;
    }

    public function using(string $preset) : static {
        $this->llm->using($preset);
        return $this;
    }

    // ACCESSORS ////////////////////////////////////////////////////////

    /**
     * Returns the config object for the current instance.
     *
     * @return StructuredOutputConfig The config object for the current instance.
     */
    public function config() : StructuredOutputConfig {
        return $this->config;
    }

    /**
     * Returns LLM configuration object for the current instance.
     *
     * @return LLM The LLM object for the current instance.
     */
    public function llm() : LLM {
        return $this->llm;
    }

    // DEPRECATED  //////////////////////////////////////////////////////

    #[Deprecated('To be replaced with request() accessor')]
    public function getRequest() : StructuredOutputRequest {
        return $this->request;
    }
}