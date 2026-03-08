<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeminiDriver extends BaseInferenceRequestDriver
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
            requestTranslator: new GeminiRequestAdapter(
                $config,
                new GeminiBodyFormat(
                    $config,
                    new GeminiMessageFormat(),
                )
            ),
            responseTranslator: new GeminiResponseAdapter(
                new GeminiUsageFormat()
            ),
        );
    }

    /**
     * Gemini does not support response_format alongside tools.
     */
    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities(
            streaming: true,
            toolCalling: true,
            toolChoice: true,
            responseFormatJsonObject: true,
            responseFormatJsonSchema: true,
            responseFormatWithTools: false,
        );
    }
}
