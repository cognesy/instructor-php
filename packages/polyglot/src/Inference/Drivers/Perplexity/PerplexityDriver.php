<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Perplexity;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

class PerplexityDriver extends BaseInferenceRequestDriver
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
                new PerplexityBodyFormat(
                    $config,
                    new OpenAIMessageFormat(),
                )
            ),
            responseTranslator: new OpenAIResponseAdapter(
                new OpenAIUsageFormat()
            ),
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