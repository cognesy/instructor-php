<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Logging\EventLogger;
use Cognesy\Instructor\Utils\Env;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    use Events\Traits\HandlesEvents;
    use Events\Traits\HandlesEventListeners;

    use Traits\HandlesApiClient;
    use Traits\HandlesConfig;
    use Traits\HandlesDebug;
    use Traits\HandlesEnv;
    use Traits\HandlesErrors;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesQueuedEvents;
    use Traits\HandlesRequest;
    use Traits\HandlesSchema;
    use Traits\HandlesSequenceUpdates;
    use Traits\HandlesTimer;

    private LoggerInterface $logger;
    private EventLogger $eventLogger;
    private ApiRequestConfig $apiRequestConfig;

    public function __construct(array $config = []) {
        $this->queueEvent(new InstructorStarted($config));
        // try loading .env (if paths are set)
        Env::load();
        $this->config = Configuration::fresh($config);
        $this->events = $this->config->get(EventDispatcher::class);
        $this->clientFactory = $this->config->get(ApiClientFactory::class);
        $this->clientFactory->setDefault($this->config->get(CanCallApi::class));
        $this->requestFactory = /** @var RequestFactory */ $this->config->get(RequestFactory::class);
        $this->responseModelFactory = $this->config->get(ResponseModelFactory::class);
        $this->apiRequestConfig = $this->config->get(ApiRequestConfig::class);
        //$this->logger = $this->config->get(LoggerInterface::class);
        //$this->eventLogger = $this->config->get(EventLogger::class);
        //$this->events->wiretap($this->eventLogger->eventListener(...));
        $this->queueEvent(new InstructorReady($this->config));
    }

    /// EXTRACTION EXECUTION ENDPOINTS ////////////////////////////////////////

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respond(
        string|array $messages = '',
        string|array|object $input = [],
        string|object|array $responseModel = [],
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
            input: $input,
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
        string|array $messages = '',
        string|array|object $input = [],
        string|object|array $responseModel = [],
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
        $this->request = $this->requestFactory->create(
            messages: $messages,
            input: $input,
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
        $this->dispatchQueuedEvents();
        $this->events->dispatch(new RequestReceived($this->getRequest()));
        return $this;
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->getRequest() === null) {
            throw new Exception('Request not defined, call request() first');
        }

        $isStream = $this->getRequest()->option(key: 'stream', defaultValue: false);
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
        if ($this->getRequest() === null) {
            throw new Exception('Request not defined, call request() first');
        }

        // TODO: do we need this? cannot we just turn streaming on?
        $isStream = $this->getRequest()->option('stream', false);
        if (!$isStream) {
            throw new Exception('Instructor::stream() method requires response streaming: set "stream" = true in the request options.');
        }

        return new Stream($this->handleStreamRequest(), $this->events());
    }
}
