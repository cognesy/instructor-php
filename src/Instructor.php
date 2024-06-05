<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Logging\EventLogger;
use Cognesy\Instructor\Utils\Env;
use Exception;
use Psr\Log\LoggerInterface;
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
    use Traits\HandlesDebug;
    use Traits\HandlesErrors;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesQueuedEvents;
    use Traits\HandlesSchema;
    use Traits\HandlesSequenceUpdates;
    use Traits\HandlesTimer;

    protected ?Request $request = null;
    protected RequestFactory $requestFactory;
    protected ApiClientFactory $clientFactory;
    protected ResponseModelFactory $responseModelFactory;
    private LoggerInterface $logger;
    private EventLogger $eventLogger;

    public function __construct(array $config = []) {
        $this->queueEvent(new InstructorStarted($config));
        // try loading .env (if paths are set)
        Env::load();
        $this->config = Configuration::fresh($config);
        $this->events = $this->config->get(EventDispatcher::class);
        $this->clientFactory = $this->config->get(ApiClientFactory::class);
        $this->clientFactory->setDefault($this->config->get(CanCallApi::class));
        $this->requestFactory = $this->config->get(RequestFactory::class);
        $this->responseModelFactory = $this->config->get(ResponseModelFactory::class);
        //$this->logger = $this->config->get(LoggerInterface::class);
        //$this->eventLogger = $this->config->get(EventLogger::class);
        //$this->events->wiretap($this->eventLogger->eventListener(...));
        $this->queueEvent(new InstructorReady($this->config));
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

    public function client() : CanCallApi {
        return $this->clientFactory->getDefault();
    }

    public function withClient(CanCallApi $client) : self {
        $this->clientFactory->setDefault($client);
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
        array $examples = [],
        string $toolName = '',
        string $toolDescription = '',
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools
    ) : mixed {
        $this->request(
            messages: $messages,
            responseModel: $responseModel,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            examples: $examples,
            toolName: $toolName,
            toolDescription: $toolDescription,
            prompt: $prompt,
            retryPrompt: $retryPrompt,
            mode: $mode,
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
        array $examples = [],
        string $toolName = '',
        string $toolDescription = '',
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
    ) : ?self {
        if (empty($responseModel)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }
        $request = $this->requestFactory->create(
            messages: $messages,
            responseModel: $responseModel,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            examples: $examples,
            toolName: $toolName,
            toolDescription: $toolDescription,
            prompt: $prompt,
            retryPrompt: $retryPrompt,
            mode: $mode,
        );
        $this->request = $request;
        $this->dispatchQueuedEvents();
        $this->events->dispatch(new RequestReceived($request));
        return $this;
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->request === null) {
            throw new Exception('Request not defined, call request() first');
        }
        $isStream = $this->request->option('stream', false);
        if ($isStream) {
            return $this->stream()->final();
        }
        $result = $this->handleRequest();
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result;
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : Stream {
        if ($this->request === null) {
            throw new Exception('Request not defined, call request() first');
        }
        $isStream = $this->request->option('stream', false);
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
            /** @var StreamRequestHandler $streamHandler */
            $streamHandler = $this->config()->get(CanHandleStreamRequest::class);
            $this->startTimer();
            yield from $streamHandler->respondTo($this->getRequest());
            $this->stopTimer();
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function getRequest() : Request {
        if ($this->debug()) {
            $this->request->setOption('debug', true);
        }
        return $this->requestFactory->fromRequest($this->request);
    }
}
