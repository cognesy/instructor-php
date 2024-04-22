<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Utils\Env;
use Exception;
use Throwable;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    use Events\Traits\HandlesEvents;
    use Events\Traits\HandlesEventListeners;
    use Traits\HandlesConfig;
    use Traits\HandlesQueuedEvents;
    use Traits\HandlesErrors;
    use Traits\HandlesSequenceUpdates;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesApiClient;
    use Traits\HandlesTimer;

    protected Request $request;

    public function __construct(array $config = []) {
        $this->queueEvent(new InstructorStarted($config));
        // try loading .env (if paths are set)
        Env::load();
        $configuration = Configuration::fresh($config);
        $this->setConfig($configuration);
        $this->withEventDispatcher($this->config()->get(EventDispatcher::class));
        $this->queueEvent(new InstructorReady($this->config()));
    }

    /// INITIALIZATION ENDPOINTS //////////////////////////////////////////////

    /**
     * Sets the environment variables configuration file paths and names
     *
     * @param string|array $paths
     * @param string|array $names
     * @return $this
     */
    public function withEnv(string|array $paths, string|array $names = '') : self {
        Env::set($paths, $names);
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

    /// EXTRACTION EXECUTION ENDPOINTS ////////////////////////////////////////

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
            $mode,
        );
        return $this->get();
    }

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
        Mode $mode = Mode::Tools,
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
            $mode,
        );
        return $this->withRequest($request);
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->request === null) {
            throw new Exception('Request not defined, call withRequest() or request() first');
        }
        $isStream = $this->request->options['stream'] ?? false;
        if ($isStream) {
            return $this->stream()->final();
        }
        $result = $this->handleRequest();
        $this->events->dispatch(new InstructorDone());
        return $result;
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : Stream {
        if ($this->request === null) {
            throw new Exception('Request not defined, call withRequest() or request() first');
        }
        $isStream = $this->request->options['stream'] ?? false;
        if (!$isStream) {
            throw new Exception('Instructor::stream() method requires response streaming: set "stream" = true in the request options.');
        }
        return new Stream($this->handleStreamRequest(), $this->events());
    }

    /// INTERNAL //////////////////////////////////////////////////////////////

    protected function handleRequest() : mixed {
        try {
            /** @var RequestHandler $requestHandler */
            $requestHandler = $this->config()->get(CanHandleRequest::class);
            $this->startTimer();
            $response = $requestHandler->respondTo($this->getRequest());
            $this->stopTimer();
            $this->events->dispatch(new ResponseGenerated($response));
            return $response;
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleStreamRequest() : Iterable {
        try {
            /** @var StreamRequestHandler $requestHandler */
            $requestHandler = $this->config()->get(CanHandleStreamRequest::class);
            $this->startTimer();
            yield from $requestHandler->respondTo($this->getRequest());
            $this->stopTimer();
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function getRequest() : Request {
        return $this->request->withClient(
            $this->client()->withEventDispatcher($this->events())
        );
    }
}
