<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
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
class OpenResponsesDriver extends BaseInferenceDriver
{
    protected CanTranslateInferenceRequest $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->requestTranslator = new OpenResponsesRequestAdapter(
            $config,
            new OpenResponsesBodyFormat(
                $config,
                new OpenResponsesMessageFormat(),
            )
        );
        $this->responseTranslator = new OpenResponsesResponseAdapter(
            new OpenResponsesUsageFormat()
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
