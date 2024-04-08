<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Configuration\ComponentConfig;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\SequenceUpdated;
use Cognesy\Instructor\Utils\Env;
use Exception;
use Throwable;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    private Configuration $config;
    private EventDispatcher $events;
    private $onError;
    private $onPartialResponse;
    private $onSequenceUpdate;
    private $queuedEvents = [];
    private Request $request;


    public function __construct(array $config = []) {
        $this->queuedEvents[] = new InstructorStarted($config);
        // load .env (if paths are set)
        Env::load();
        /** @var Configuration */
        $this->config = Configuration::fresh($config);
        $this->queuedEvents[] = new InstructorReady($this->config);
        /** @var \Cognesy\Instructor\Events\EventDispatcher */
        $this->events = $this->config->get(EventDispatcher::class);
    }

    /// INITIALIZATION ENDPOINTS /////////////////////////////////////////////////////////////

    /**
     * Overrides the default configuration
     */
    public function withConfig(array $config) : self {
        $this->config->override($config);
        return $this;
    }

    /**
     * Sets the request to be used for the next call
     */
    public function withRequest(Request $request) : self {
        $this->dispatchQueuedEvents();
        $this->request = $request;
        $this->events->dispatch(new RequestReceived($request));
        return $this;
    }

    public function withEnv(string|array $paths, string|array $names = '') : self {
        Env::set($paths, $names);
        return $this;
    }

    public function withClient(CanCallApi $client) : self {
        $this->config->override([
            CanCallApi::class => $client->withEventDispatcher($this->events)
        ]);
        return $this;
    }

    /// EXTRACTION EXECUTION ENDPOINTS ///////////////////////////////////////////////////////

    /**
     * Creates the request to be executed
     */
    public function request(
        string|array $messages,
        string|object|array $responseModel,
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $functionName = 'extract_data',
        string $functionDescription = 'Extract data from provided content',
        string $retryPrompt = "Recall function correctly, fix following errors",
        Mode $mode = Mode::Tools
    ) : self {
        $request = new Request(
            $messages,
            $responseModel,
            $model,
            $maxRetries,
            $options,
            $functionName,
            $functionDescription,
            $retryPrompt,
            $mode
        );
        return $this->withRequest($request);
    }

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respond(
        string|array $messages,
        string|object|array $responseModel,
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $functionName = 'extract_data',
        string $functionDescription = 'Extract data from provided content',
        string $retryPrompt = "Recall function correctly, fix following errors",
        Mode $mode = Mode::Tools
    ) : mixed {
        $this->request(
            $messages,
            $responseModel,
            $model,
            $maxRetries,
            $options,
            $functionName,
            $functionDescription,
            $retryPrompt,
            $mode
        );
        return $this->get();
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->request === null) {
            throw new Exception('Request not defined, call withRequest() or request() first');
        }
        try {
            /** @var CanHandleRequest */
            $requestHandler = $this->config->get(CanHandleRequest::class);
            $responseResult = $requestHandler->respondTo($this->request);
            if ($responseResult->isFailure()) {
                throw new Exception($responseResult->error());
            }
            $this->events->dispatch(new ResponseGenerated($responseResult->unwrap()));
            return $responseResult->unwrap();
        } catch (Throwable $error) {
            // if anything goes wrong, we first dispatch an event (e.g. to log error)
            $event = new ErrorRaised($error, $this->request);
            $this->events->dispatch($event);
            if (isset($this->onError)) {
                // final attempt to recover from the error (e.g. give fallback response)
                return ($this->onError)($event);
            }
            throw $error;
        }
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : Iterable {
        if ($this->request === null) {
            throw new Exception('Request not defined, call withRequest() or request() first');
        }
        try {
            /** @var StreamRequestHandler $requestHandler */
            $requestHandler = $this->config->get(CanHandleStreamRequest::class);
            yield from $requestHandler->respondTo($this->request);
        } catch (Throwable $error) {
            // if anything goes wrong, we first dispatch an event (e.g. to log error)
            $event = new ErrorRaised($error, $this->request);
            $this->events->dispatch($event);
            if (isset($this->onError)) {
                // final attempt to recover from the error (e.g. give fallback response)
                return ($this->onError)($event);
            }
            throw $error;
        }
    }

    public function streamAll() : mixed {
        $stream = $this->stream();
        $result = null;
        foreach ($stream as $item) {
            $result = $item;
        }
        return $result;
    }

    /// ACCESS CURRENT INSTRUCTOR CONFIGURATION //////////////////////////////////////////////

    /**
     * Returns the current configuration
     */
    public function getConfig() : Configuration {
        return $this->config;
    }

    /**
     * Returns the current configuration
     */
    public function getComponentConfig(string $component) : ?ComponentConfig {
        if (!$this->config->has($component)) {
            return null;
        }
        return $this->config->getConfig($component);
    }

    /**
     * Returns the current configuration
     */
    public function getComponent(string $component) : ?object {
        if (!$this->config->has($component)) {
            return null;
        }
        return $this->config->get($component);
    }


    /// EVENT HANDLERS ///////////////////////////////////////////////////////////////////////

    /**
     * Listens to all events
     */
    public function wiretap(callable $listener) : self {
        $this->events->wiretap($listener);
        return $this;
    }

    /**
     * Listens to a specific event
     */
    public function onEvent(string $class, callable $listener) : self {
        $this->events->addListener($class, $listener);
        return $this;
    }

    /**
     * Listens to Instructor execution error
     */
    public function onError(callable $listener) : self {
        $this->onError = $listener;
        return $this;
    }

    /**
     * Listens to partial responses
     */
    public function onPartialUpdate(callable $listener) : self {
        $this->onPartialResponse = $listener;
        $this->events->addListener(
            PartialResponseGenerated::class,
            $this->handlePartialResponse(...)
        );
        return $this;
    }

    /**
     * Listens to sequence updates
     */
    public function onSequenceUpdate(callable $listener) : self {
        $this->onSequenceUpdate = $listener;
        $this->events->addListener(
            SequenceUpdated::class,
            $this->handleSequenceUpdate(...)
        );
        return $this;
    }

    /// PRIVATE //////////////////////////////////////////////////////////////////////////////

    /**
     * Provides partial response instead of event - for developer convenience
     */
    private function handlePartialResponse(PartialResponseGenerated $event) : void {
        if (!is_null($this->onPartialResponse)) {
            ($this->onPartialResponse)($event->partialResponse);
        }
    }

    /**
     * Provides sequence instead of event - for developer convenience
     */
    private function handleSequenceUpdate(SequenceUpdated $event) : void {
        if (!is_null($this->onSequenceUpdate)) {
            ($this->onSequenceUpdate)($event->sequence);
        }
    }

    /**
     * Dispatches all events queued before $events was initialized
     */
    private function dispatchQueuedEvents()
    {
        foreach ($this->queuedEvents as $event) {
            $this->events->dispatch($event);
        }
        $this->queuedEvents = [];
    }
}
