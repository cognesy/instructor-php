<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\Core\Factories\ModelFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Data\Messages\Utils\ScriptFactory;
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
    use Traits\Request\HandlesInput;
    use Traits\Request\HandlesMessages;
    use Traits\Request\HandlesMode;
    use Traits\Request\HandlesModel;
    use Traits\Request\HandlesPrompts;
    use Traits\Request\HandlesRequestedModel;
    use Traits\Request\HandlesRetries;

    public function __construct(
        string|array $messages,
        string|array|object $input,
        string|object|array $responseModel,
        string|ModelParams $model,
        int $maxRetries,
        array $options,
        array $examples,
        string $toolName,
        string $toolDescription,
        string $prompt,
        string $retryPrompt,
        Mode $mode,
        CanCallApi $client,
        ModelFactory $modelFactory,
        ResponseModelFactory $responseModelFactory,
        ApiClientFactory $clientFactory,
        ApiRequestFactory $apiRequestFactory,
        ApiRequestConfig $requestConfig,
    ) {
        $this->modelFactory = $modelFactory;
        $this->responseModelFactory = $responseModelFactory;
        $this->apiRequestFactory = $apiRequestFactory;
        $this->requestConfig = $requestConfig;

//        $this->requestConfig->withDebug($this->options['debug'] ?? false);
//        unset($options['debug']);
//        $this->requestConfig->cacheConfig()->setEnabled($this->options['cache'] ?? false);
//        unset($options['cache']);
//        $this->options = $options;

        $this->client = $client ?? $clientFactory->getDefault();
        $this->withModel($model);
        if (empty($this->option('max_tokens'))) {
            $this->setOption('max_tokens', $this->client->defaultMaxTokens);
        }

        $this->maxRetries = $maxRetries;
        $this->mode = $mode;

        $this->input = $input;
        $this->messages = $this->normalizeMessages($messages);
        $this->prompt = $prompt;
        $this->retryPrompt = $retryPrompt ?: $this->defaultRetryPrompt;
        $this->examples = $examples;

        $this->toolName = $toolName ?: $this->defaultToolName;
        $this->toolDescription = $toolDescription ?: $this->defaultToolDescription;
        $this->requestedSchema = $responseModel;
        $this->responseModel = $this->responseModelFactory->fromAny(
            $this->requestedSchema(),
            $this->toolName(),
            $this->toolDescription()
        );

//        $this->script = Script::fromArray([
//            'messages' => $this->messages,
//            'command' => $this->prompt(),
//            'examples' => $this->examplesPrompt,
//            'retry_prompt' => $this->retryPrompt,
//        ]);

        $this->script = ScriptFactory::make(
            $this->messages(),
            $this->input(),
            $this->dataAckPrompt,
            $this->prompt(),
            $this->examples(),
            $this->retryPrompt(),
        );
    }

    public function copy(array $messages) : self {
        return (clone $this)->withMessages($messages);
    }
}
