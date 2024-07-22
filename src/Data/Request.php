<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\Core\Factories\ModelFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Enums\Mode;

class Request
{
    use Traits\Request\HandlesApiClient;
    use Traits\Request\HandlesApiRequestConfig;
    use Traits\Request\HandlesApiRequestData;
    use Traits\Request\HandlesApiRequestEndpoint;
    use Traits\Request\HandlesApiRequestFactory;
    use Traits\Request\HandlesApiRequestMethod;
    use Traits\Request\HandlesApiRequestOptions;
    use Traits\Request\HandlesExamples;
    use Traits\Request\HandlesMessages;
    use Traits\Request\HandlesMode;
    use Traits\Request\HandlesModel;
    use Traits\Request\HandlesPrompts;
    use Traits\Request\HandlesRetries;
    use Traits\Request\HandlesSchema;

    public function __construct(
        string|array $messages,
        string|array|object $input,
        string|array|object $responseModel,
        string $system,
        string $prompt,
        array $examples,
        string|ModelParams $model,
        int $maxRetries,
        array $options,
        string $toolName,
        string $toolDescription,
        string $retryPrompt,
        Mode $mode,
        CanCallApi $client,
        ModelFactory $modelFactory,
        ResponseModelFactory $responseModelFactory,
        ApiRequestFactory $apiRequestFactory,
        ApiRequestConfig $requestConfig,
    ) {
        $this->modelFactory = $modelFactory;
        $this->responseModelFactory = $responseModelFactory;
        $this->apiRequestFactory = $apiRequestFactory;
        $this->requestConfig = $requestConfig;
        $this->client = $client;

        $this->options = $options;
        $this->maxRetries = $maxRetries;
        $this->mode = $mode;

        $this->input = $input;
        $this->messages = $this->normalizeMessages($messages);
        $this->system = $system;
        $this->prompt = $prompt;
        $this->retryPrompt = $retryPrompt;
        $this->examples = $examples;

        $this->withModel($model);
        if (empty($this->option('max_tokens'))) {
            $this->setOption('max_tokens', $this->client->defaultMaxTokens());
        }

        $this->toolName = $toolName ?: $this->defaultToolName;
        $this->toolDescription = $toolDescription ?: $this->defaultToolDescription;
        $this->requestedSchema = $responseModel;
        if (!empty($this->requestedSchema)) {
            $this->responseModel = $this->responseModelFactory->fromAny(
                $this->requestedSchema(),
                $this->toolName(),
                $this->toolDescription()
            );
        }
    }

    public function copy(array $messages) : self {
        return (clone $this)->withMessages($messages);
    }
}
