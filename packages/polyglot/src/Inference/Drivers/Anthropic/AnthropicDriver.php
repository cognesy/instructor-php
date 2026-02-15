<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

class AnthropicDriver extends BaseInferenceRequestDriver
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
            requestTranslator: new AnthropicRequestAdapter(
                $config,
                new AnthropicBodyFormat(
                    $config,
                    new AnthropicMessageFormat(),
                )
            ),
            responseTranslator: new AnthropicResponseAdapter(
                new AnthropicUsageFormat()
            ),
        );
    }

    /**
     * Anthropic does not support native JSON schema mode or response_format.
     * Use Tools or MdJson modes for structured output.
     */
    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities(
            outputModes: [OutputMode::Tools, OutputMode::MdJson, OutputMode::Text, OutputMode::Unrestricted],
            streaming: true,
            toolCalling: true,
            jsonSchema: false,
            responseFormatWithTools: false,
        );
    }
}