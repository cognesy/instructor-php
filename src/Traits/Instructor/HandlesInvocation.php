<?php
namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\InstructorResponse;
use Cognesy\Instructor\Stream;
use Exception;

trait HandlesInvocation
{
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
        $instructorResponse = $this->request(
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
        return $instructorResponse->get();
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
    ) : InstructorResponse {
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
        $this->dispatchQueuedEvents();
        return new InstructorResponse(
            request: $this->requestFromData(
                $this->requestData->withCachedContext($this->cachedContext)
            ),
            requestHandler: $this->requestHandler,
            events: $this->events,
        );
    }
}