<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Core\EventDispatcher;
use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseSent;
use Cognesy\Instructor\Utils\Configuration;
use Throwable;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    public Configuration $config;
    private $onError;

    public function __construct(array $config = []) {
        $this->config = Configuration::fresh($config);
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
            // create request event immediately, so we get accurate timestamp
            $request = new Request($messages, $responseModel, $model, $maxRetries, $options);
            // initialize config - if not already done
            if (!isset($this->config)) {
                $this->config = Configuration::fresh();
            }
            // get event dispatcher and wiretap it
            /** @var EventDispatcher $eventDispatcher */
            $eventDispatcher = $this->config->get(EventDispatcher::class);
            // ...only now we dispatch the request received event
            $eventDispatcher->dispatch(new RequestReceived($request));
            // now we can handle the request
            $requestHandler = $this->config->get(RequestHandler::class);
            $response = $requestHandler->respond($request);
            $eventDispatcher->dispatch(new ResponseSent($response));
            // ...and return the response
            return $response;
        } catch (Throwable $error) {
            // if anything goes wrong, we first dispatch an event (e.g. to log error)
            $eventDispatcher->dispatch(new ErrorRaised($error));
            if (isset($this->onError)) {
                // final attempt to recover from the error (e.g. give fallback response)
                return ($this->onError)($request, $error);
            } else {
                throw $error;
            }
        }
    }

    public function wiretap(callable $listener) : self {
        $eventDispatcher = $this->config->get(EventDispatcher::class);
        $eventDispatcher->wiretap($listener);
        return $this;
    }

    public function onEvent(string $class, callable $listener) : self {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->config->get(EventDispatcher::class);
        $eventDispatcher->addListener($class, $listener);
        return $this;
    }

    public function onError(callable $listener) : self {
        $this->onError = $listener;
        return $this;
    }
}
