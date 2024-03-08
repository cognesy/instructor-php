<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Core\EventDispatcher;
use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseReturned;
use Cognesy\Instructor\Utils\Configuration;
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

    public function __construct(array $config = []) {
        $this->queuedEvents[] = new InstructorStarted($config);
        $this->config = Configuration::fresh($config);
        $this->queuedEvents[] = new InstructorReady($this->config);
        $this->eventDispatcher = $this->config->get(EventDispatcher::class);
    }

    public function withConfig(array $config) : self {
        $this->config->override($config);
        return $this;
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
    ) : mixed {
        try {
            $request = new Request($messages, $responseModel, $model, $maxRetries, $options);
            $this->dispatchQueuedEvents();
            $this->eventDispatcher->dispatch(new RequestReceived($request));
            $requestHandler = $this->config->get(RequestHandler::class);
            $response = $requestHandler->respond($request);
            $this->eventDispatcher->dispatch(new ResponseReturned($response));
            return $response;
        } catch (Throwable $error) {
            // if anything goes wrong, we first dispatch an event (e.g. to log error)
            $this->eventDispatcher->dispatch(new ErrorRaised($error));
            if (isset($this->onError)) {
                // final attempt to recover from the error (e.g. give fallback response)
                return ($this->onError)($request, $error);
            }
            throw $error;
        }
    }

    public function wiretap(callable $listener) : self {
        $this->eventDispatcher->wiretap($listener);
        return $this;
    }

    public function onEvent(string $class, callable $listener) : self {
        $this->eventDispatcher->addListener($class, $listener);
        return $this;
    }

    public function onError(callable $listener) : self {
        $this->onError = $listener;
        return $this;
    }

    private function dispatchQueuedEvents()
    {
        foreach ($this->queuedEvents as $event) {
            $this->eventDispatcher->dispatch($event);
        }
        $this->queuedEvents = [];
    }
}
