<?php
namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;

class RequestFactory
{
    public function __construct(
        protected ResponseModelFactory $responseModelFactory,
        protected EventDispatcher $events,
    ) {}

    public function fromData(
        RequestInfo $data,
    ) : Request {
        return $this->create(
            messages: $data->messages ?? [],
            input: $data->input ?? [],
            responseModel: $data->responseModel ?? [],
            system: $data->system ?? '',
            prompt: $data->prompt ?? '',
            examples: $data->examples ?? [],
            model: $data->model ?? '',
            maxRetries: $data->maxRetries ?? 0,
            options: $data->options ?? [],
            toolName: $data->toolName ?? '',
            toolDescription: $data->toolDescription ?? '',
            retryPrompt: $data->retryPrompt ?? '',
            mode: $data->mode ?? Mode::Tools,
            cachedContext: $data->cachedContext ?? [],
            connection: $data->connection ?? '',
        );
    }

    public function create(
        string|array $messages = [],
        string|array|object $input = '',
        string|array|object $responseModel = [],
        string $system = '',
        string $prompt = '',
        array $examples = [],
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $toolName = '',
        string $toolDescription = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
        array $cachedContext = [],
        string $connection = '',
    ) : Request {
        return new Request(
            messages: $messages,
            input: $input,
            responseModel: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            toolName: $toolName,
            toolDescription: $toolDescription,
            retryPrompt: $retryPrompt,
            mode: $mode,
            cachedContext: $cachedContext,
            connection: $connection,
            responseModelFactory: $this->responseModelFactory,
        );
    }
}
