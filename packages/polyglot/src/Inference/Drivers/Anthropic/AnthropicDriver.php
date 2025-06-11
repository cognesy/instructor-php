<?php

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class AnthropicDriver extends BaseInferenceDriver
{
    protected ProviderRequestAdapter  $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->requestAdapter = new AnthropicRequestAdapter(
            $config,
            new AnthropicBodyFormat(
                $config,
                new AnthropicMessageFormat(),
            )
        );
        $this->responseAdapter = new AnthropicResponseAdapter(
            new AnthropicUsageFormat()
        );
    }
}