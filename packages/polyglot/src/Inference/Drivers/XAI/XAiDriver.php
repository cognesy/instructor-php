<?php

namespace Cognesy\Polyglot\Inference\Drivers\XAI;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class XAiDriver extends BaseInferenceDriver
{
    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    )
    {
        $this->requestAdapter = new OpenAIRequestAdapter(
            $config,
            new OpenAICompatibleBodyFormat(
                $config,
                new XAiMessageFormat(),
            )
        );
        $this->responseAdapter = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
    }
}