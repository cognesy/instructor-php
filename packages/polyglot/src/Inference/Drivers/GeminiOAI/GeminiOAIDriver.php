<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\GeminiOAI;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeminiOAIDriver extends BaseInferenceRequestDriver
{
    public function __construct(
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
    ) {
        parent::__construct(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: new GeminiOAIRequestAdapter(
                $config,
                new GeminiOAIBodyFormat(
                    $config,
                    new OpenAIMessageFormat(),
                )
            ),
            responseTranslator: new OpenAIResponseAdapter(
                new GeminiOAIUsageFormat()
            ),
        );
    }

    /**
     * GeminiOAI does not support native JSON schema or response_format alongside tools.
     */
    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities(
            streaming: true,
            toolCalling: true,
            toolChoice: true,
            responseFormatJsonObject: true,
            responseFormatJsonSchema: false,
            responseFormatWithTools: false,
        );
    }
}
