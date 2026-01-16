<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Perplexity;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

class PerplexityDriver extends BaseInferenceDriver
{
    protected CanTranslateInferenceRequest $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    )
    {
        $this->requestTranslator = new OpenAIRequestAdapter(
            $config,
            new PerplexityBodyFormat(
                $config,
                new OpenAIMessageFormat(),
            )
        );
        $this->responseTranslator = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
    }

    /**
     * Perplexity does not support tool calling.
     */
    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities(
            outputModes: [OutputMode::Json, OutputMode::JsonSchema, OutputMode::MdJson, OutputMode::Text, OutputMode::Unrestricted],
            streaming: true,
            toolCalling: false,
            jsonSchema: true,
            responseFormatWithTools: false,
        );
    }
}