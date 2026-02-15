<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Base driver for OpenResponses API specification.
 *
 * This driver implements the OpenResponses specification which is an open standard
 * based on OpenAI's Responses API, designed for multi-provider interoperability.
 *
 * @see https://www.openresponses.org/
 */
class OpenResponsesDriver extends BaseInferenceRequestDriver
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
            requestTranslator: new OpenResponsesRequestAdapter(
                $config,
                new OpenResponsesBodyFormat(
                    $config,
                    new OpenResponsesMessageFormat(),
                )
            ),
            responseTranslator: new OpenResponsesResponseAdapter(
                new OpenResponsesUsageFormat()
            ),
        );
    }

    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities(
            outputModes: OutputMode::cases(),
            streaming: true,
            toolCalling: true,
            jsonSchema: true,
            responseFormatWithTools: true,
        );
    }
}
