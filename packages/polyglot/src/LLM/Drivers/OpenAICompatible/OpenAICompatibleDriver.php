<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAICompatible;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIUsageFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class OpenAICompatibleDriver extends BaseInferenceDriver
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
                new OpenAIMessageFormat(),
            )
        );
        $this->responseAdapter = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
    }
}