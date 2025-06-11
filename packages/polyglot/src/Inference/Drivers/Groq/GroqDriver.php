<?php

namespace Cognesy\Polyglot\Inference\Drivers\Groq;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Psr\EventDispatcher\EventDispatcherInterface;

class GroqDriver extends BaseInferenceDriver
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
            new GroqBodyFormat(
                $config,
                new OpenAIMessageFormat(),
            )
        );
        $this->responseAdapter = new OpenAIResponseAdapter(
            new GroqUsageFormat()
        );
    }
}