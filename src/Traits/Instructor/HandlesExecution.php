<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Stream;
use Exception;

trait HandlesExecution
{
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