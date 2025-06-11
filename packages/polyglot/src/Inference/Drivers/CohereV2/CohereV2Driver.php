<?php

namespace Cognesy\Polyglot\Inference\Drivers\CohereV2;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class CohereV2Driver extends BaseInferenceDriver
{
    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    )
    {
        $this->requestAdapter = new CohereV2RequestAdapter(
            $config,
            new CohereV2BodyFormat(
                $config,
                new OpenAIMessageFormat(),
            )
        );
        $this->responseAdapter = new CohereV2ResponseAdapter(
            new CohereV2UsageFormat()
        );
    }
}