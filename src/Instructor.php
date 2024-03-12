<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Core\Data\Request;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseReturned;
use Iterator;
use Throwable;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    public Configuration $config;
    private EventDispatcher $eventDispatcher;
    private $onError;
    private $queuedEvents = [];
    private Request $request;

    public function __construct(array $config = []) {
        $this->queuedEvents[] = new InstructorStarted($config);
        /** @var Configuration */
        $this->config = Configuration::fresh($config);
        $this->queuedEvents[] = new InstructorReady($this->config);
        /** @var \Cognesy\Instructor\Events\EventDispatcher */
        $this->eventDispatcher = $this->config->get(EventDispatcher::class);
    }

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
        $this->request = $request;
        return $this;
    }

    public function request(
        string|array $messages,
        string|object|array $responseModel,
        string $model = 'gpt-4-0125-preview',
        int $maxRetries = 0,
        array $options = [],
        string $functionName = 'extract_data',
        string $functionDescription = 'Extract data from provided content',
        string $retryPrompt = "Recall function correctly, fix following errors:"
    ) : self {
        $this->dispatchQueuedEvents();
        $this->request = new Request($messages, $responseModel, $model, $maxRetries, $options, $functionName, $functionDescription, $retryPrompt);
        $this->eventDispatcher->dispatch(new RequestReceived(new Request($messages, $responseModel, $model, $maxRetries, $options)));
        return $this;
    }

    /**
     * Executes the request and returns the response as a stream
     */
    public function stream() : Iterator {
        $this->request->options['stream'] = true;
        return $this->get(stream: true);
    }

    /**
     * Executes the request and returns the response
     */
    public function get(bool $stream = false) : mixed {
        try {
            /** @var CanHandleRequest */
            $requestHandler = $this->config->get(CanHandleRequest::class);
            $response = $requestHandler->respondTo($this->request);
            $this->eventDispatcher->dispatch(new ResponseReturned($response));
            return $response;
        } catch (Throwable $error) {
            // if anything goes wrong, we first dispatch an event (e.g. to log error)
            $event = new ErrorRaised($error, $this->request);
            $this->eventDispatcher->dispatch($event);
            if (isset($this->onError)) {
                // final attempt to recover from the error (e.g. give fallback response)
                return ($this->onError)($event);
            }
            throw $error;
        }
    }

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respond(
        string|array $messages,
        string|object|array $responseModel,
        string $model = 'gpt-4-0125-preview',
        int $maxRetries = 0,
        array $options = [],
        string $functionName = 'extract_data',
        string $functionDescription = 'Extract data from provided content',
        string $retryPrompt = "Recall function correctly, fix following errors:"
    ) : mixed {
        $this->request($messages, $responseModel, $model, $maxRetries, $options, $functionName, $functionDescription, $retryPrompt)->get();
        return $this->get();
    }

    /**
     * Adds a listener to all events
     */
    public function wiretap(callable $listener) : self {
        $this->eventDispatcher->wiretap($listener);
        return $this;
    }

    /**
     * Adds a listener to a specific event
     */
    public function onEvent(string $class, callable $listener) : self {
        $this->eventDispatcher->addListener($class, $listener);
        return $this;
    }

    /**
     * Adds a listener to any error
     */
    public function onError(callable $listener) : self {
        $this->onError = $listener;
        return $this;
    }

    /**
     * Dispatches all queued events
     */
    private function dispatchQueuedEvents()
    {
        foreach ($this->queuedEvents as $event) {
            $this->eventDispatcher->dispatch($event);
        }
        $this->queuedEvents = [];
    }
}
