<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

class AnthropicDriver extends BaseInferenceDriver
{
    protected CanTranslateInferenceRequest  $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->requestTranslator = new AnthropicRequestAdapter(
            $config,
            new AnthropicBodyFormat(
                $config,
                new AnthropicMessageFormat(),
            )
        );
        $this->responseTranslator = new AnthropicResponseAdapter(
            new AnthropicUsageFormat()
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