<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Deepseek;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Str;
use Psr\EventDispatcher\EventDispatcherInterface;

class DeepseekDriver extends BaseInferenceRequestDriver
{
    public function __construct(
        LLMConfig $config,
        HttpClient $httpClient,
        EventDispatcherInterface $events,
    ) {
        parent::__construct(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: new OpenAIRequestAdapter(
                $config,
                new DeepseekBodyFormat(
                    $config,
                    new OpenAIMessageFormat(),
                )
            ),
            responseTranslator: new DeepseekResponseAdapter(
                new OpenAIUsageFormat()
            ),
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
