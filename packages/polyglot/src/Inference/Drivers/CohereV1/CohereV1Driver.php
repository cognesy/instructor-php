<?php

namespace Cognesy\Polyglot\Inference\Drivers\CohereV1;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class CohereV1Driver extends BaseInferenceDriver
{
    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    )
    {
        $this->requestAdapter = new CohereV1RequestAdapter(
            $config,
            new CohereV1BodyFormat(
                $config,
                new CohereV1MessageFormat(),
            )
        );
        $this->responseAdapter = new CohereV1ResponseAdapter(
            new CohereV1UsageFormat()
        );
    }
}