<?php

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeminiDriver extends BaseInferenceDriver
{
    protected CanTranslateInferenceRequest $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    )
    {
        $this->requestTranslator = new GeminiRequestAdapter(
            $config,
            new GeminiBodyFormat(
                $config,
                new GeminiMessageFormat(),
            )
        );
        $this->responseTranslator = new GeminiResponseAdapter(
            new GeminiUsageFormat()
        );
    }
}