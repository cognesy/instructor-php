<?php
namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Stream;
use Exception;

trait HandlesInvocation
{
    private RequestInfo $requestData;
    private array $cachedContext = [];

    /**
     * Prepares Instructor for execution with provided request data
     */
    public function withRequest(RequestInfo $requestData) : static {
        $this->requestData = $requestData;
        return $this;
    }

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respond(
        string|array $messages = '',
        string|array|object $input = '',
        string|array|object $responseModel = [],
        string $system = '',
        string $prompt = '',
        array $examples = [],
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $toolTitle = '',
        string $toolDescription = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools
    ) : mixed {
        $this->request(
            messages: $messages,
            input: $input,
            responseModel: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            toolTitle: $toolTitle,
            toolDescription: $toolDescription,
            retryPrompt: $retryPrompt,
            mode: $mode,
        );
        return $this->get();
    }

    // TODO: to be implemented
    public function cacheContext(
        string|array $messages = '',
        string|array|object $input = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) : ?self {
        $this->cachedContext = [
            'messages' => $messages,
            'input' => $input,
            'system' => $system,
            'prompt' => $prompt,
            'examples' => $examples,
        ];
        return $this;
    }

    /**
     * Creates the request to be executed
     */
    public function request(
        string|array $messages = '',
        string|array|object $input = '',
        string|array|object $responseModel = [],
        string $system = '',
        string $prompt = '',
        array $examples = [],
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $toolTitle = '',
        string $toolDescription = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
    ) : ?self {
        if (empty($responseModel)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }
        $this->requestData = RequestInfo::with(
            messages: $messages,
            input: $input,
            responseModel: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            toolName: $toolTitle,
            toolDescription: $toolDescription,
            retryPrompt: $retryPrompt,
            mode: $mode,
        );
        $this->queueEvent(new RequestReceived($this->requestData));
        return $this;
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->requestData === null) {
            throw new Exception('Request not defined, call request() or withRequest() first');
        }

        if ($this->requestData->isStream()) {
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
        if ($this->requestData === null) {
            throw new Exception('Request not defined, call request() or withRequest() first');
        }

        // TODO: do we need this? cannot we just turn streaming on?
        if (!$this->requestData->isStream()) {
            throw new Exception('Instructor::stream() method requires response streaming: set "stream" = true in the request options.');
        }

        return new Stream($this->handleStreamRequest(), $this->events());
    }


    public function raw() : mixed {
        if ($this->requestData === null) {
            throw new Exception('Request not defined, call request() or withRequest() first');
        }

        if ($this->requestData->isStream()) {
            return $this->stream()->final();
        }

        $result = $this->handleRawRequest();
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result;
    }
}