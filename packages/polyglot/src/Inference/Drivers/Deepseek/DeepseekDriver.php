<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Deepseek;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Str;
use Psr\EventDispatcher\EventDispatcherInterface;

class DeepseekDriver extends BaseInferenceDriver
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
            new DeepseekBodyFormat(
                $config,
                new OpenAIMessageFormat(),
            )
        );
        $this->responseTranslator = new DeepseekResponseAdapter(
            new OpenAIUsageFormat()
        );
    }

    /**
     * Deepseek capabilities are model-specific.
     * Reasoner models do not support tools or structured output.
     */
    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        $model = $model ?? $this->config->model;
        $isReasoner = Str::contains($model, 'reasoner');
        $outputModes = match(true) {
            $isReasoner => [
                OutputMode::Json,
                OutputMode::MdJson,
                OutputMode::Text,
                OutputMode::Unrestricted,
            ],
            default => OutputMode::cases(),
        };

        return new DriverCapabilities(
            outputModes: $outputModes,
            streaming: true,
            toolCalling: !$isReasoner,
            jsonSchema: !$isReasoner,
            responseFormatWithTools: false,
        );
    }
}
