<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Compilation\Contracts\CompilerInput;
use Cognesy\Instructor\Compilation\Input;
use Cognesy\Instructor\Core\Factories\ModelFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Enums\Mode;

class Request
{
    use Traits\Request\HandlesApiClient;
    use Traits\Request\HandlesApiRequestFactory;
    use Traits\Request\HandlesExamples;
    use Traits\Request\HandlesMessages;
    use Traits\Request\HandlesModel;
    use Traits\Request\HandlesOptions;
    use Traits\Request\HandlesPrompts;
    use Traits\Request\HandlesRequestedModel;
    use Traits\Request\HandlesRetries;

    private Mode $mode;

    public function __construct(
        string|array $messages,
        string|object|array $responseModel,
        string|ModelParams $model = '',
        int $maxRetries = 0,
        array $options = [],
        array $examples = [],
        string $toolName = '',
        string $toolDescription = '',
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
        CanCallApi $client = null,
        ModelFactory $modelFactory = null,
        ResponseModelFactory $responseModelFactory = null,
        ApiRequestFactory $apiRequestFactory = null,
    ) {
        $this->messages = $this->normalizeMessages($messages);
        $this->requestedSchema = $responseModel;
        $this->maxRetries = $maxRetries;
        $this->options = $options;
        $this->toolName = $toolName ?: $this->defaultToolName;
        $this->toolDescription = $toolDescription ?: $this->defaultToolDescription;
        $this->mode = $mode;
        $this->client = $client;
        $this->prompt = $prompt;
        $this->retryPrompt = $retryPrompt ?: $this->defaultRetryPrompt;

        $this->modelFactory = $modelFactory;
        $this->responseModelFactory = $responseModelFactory;
        $this->apiRequestFactory = $apiRequestFactory;

        $this->withExamples($examples);
        $this->withModel($model);
        $this->withResponseModel(
            $this->responseModelFactory->fromAny(
                $this->requestedSchema(),
                $this->toolName(),
                $this->toolDescription()
            )
        );
    }

    public function mode() : Mode {
        return $this->mode;
    }

    public function copy(array $messages) : self {
        return (clone $this)->withMessages($messages);
    }

    public function toCompilerInput() : CompilerInput {
        return new Input(
            mode: $this->mode,
            model: $this->model,
            messages: $this->messages,
            responseModel: $this->responseModel,
            options: $this->options,
            examples: $this->examples,
            feedback: $this->feedback,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            prompt: $this->prompt,
            retryPrompt: $this->retryPrompt,
            signature: $this->signature,
            inputSchema: $this->inputSchema,
            inputData: $this->inputData,
        );
    }
}
