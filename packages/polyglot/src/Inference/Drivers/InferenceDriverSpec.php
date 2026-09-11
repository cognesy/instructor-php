<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateReasoning;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;
use Cognesy\Polyglot\Inference\Reasoning\UnsupportedReasoningTranslator;

/** Declarative collaborators used to construct an inference driver. */
final readonly class InferenceDriverSpec
{
    /**
     * @param  class-string<CanMapRequestBody>  $bodyFormat
     * @param  class-string<CanTranslateInferenceRequest>  $requestAdapter
     * @param  class-string<CanTranslateInferenceResponse>  $responseAdapter
     * @param  class-string<CanMapUsage>  $usageFormat
     * @param  class-string<CanMapMessages>  $messageFormat
     * @param  class-string<BaseInferenceRequestDriver>  $driverClass
     */
    public function __construct(
        public string $bodyFormat,
        public string $requestAdapter = OpenAIRequestAdapter::class,
        public string $responseAdapter = OpenAIResponseAdapter::class,
        public string $usageFormat = OpenAIUsageFormat::class,
        public string $messageFormat = OpenAIMessageFormat::class,
        public string $driverClass = BaseInferenceRequestDriver::class,
        public ?ReasoningWireFormat $reasoningWireFormat = null,
    ) {}

    public function __invoke(
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        CanHandleEvents $events,
    ): CanProcessInferenceRequest {
        return new ($this->driverClass)(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: new ($this->requestAdapter)(
                $config,
                new ReasoningBodyFormat(
                    bodyFormat: new ($this->bodyFormat)(
                        $config,
                        new ($this->messageFormat)(),
                    ),
                    translator: $this->reasoningTranslator(),
                ),
            ),
            responseTranslator: new ($this->responseAdapter)(
                new ($this->usageFormat)(),
            ),
        );
    }

    private function reasoningTranslator(): CanTranslateReasoning {
        return match ($this->reasoningWireFormat) {
            null => new UnsupportedReasoningTranslator(),
            default => new ConfiguredReasoningTranslator($this->reasoningWireFormat),
        };
    }
}
