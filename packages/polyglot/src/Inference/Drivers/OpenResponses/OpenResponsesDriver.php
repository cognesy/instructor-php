<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
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
        CanSendHttpRequests $httpClient,
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
            streaming: true,
            toolCalling: true,
            toolChoice: true,
            responseFormatJsonObject: true,
            responseFormatJsonSchema: true,
            responseFormatWithTools: true,
        );
    }
}
