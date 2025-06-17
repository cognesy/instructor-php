<?php

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class AnthropicDriver extends BaseInferenceDriver
{
    protected CanTranslateInferenceRequest  $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->requestTranslator = new AnthropicRequestAdapter(
            $config,
            new AnthropicBodyFormat(
                $config,
                new AnthropicMessageFormat(),
            )
        );
        $this->responseTranslator = new AnthropicResponseAdapter(
            new AnthropicUsageFormat()
        );
    }
}