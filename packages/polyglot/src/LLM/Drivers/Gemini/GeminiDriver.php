<?php

namespace Cognesy\Polyglot\LLM\Drivers\Gemini;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Config\LLMConfig;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\BaseInferenceDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeminiDriver extends BaseInferenceDriver
{
    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    )
    {
        $this->requestAdapter = new GeminiRequestAdapter(
            $config,
            new GeminiBodyFormat(
                $config,
                new GeminiMessageFormat(),
            )
        );
        $this->responseAdapter = new GeminiResponseAdapter(
            new GeminiUsageFormat()
        );
    }
}