<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAIResponses;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * OpenAI Responses API driver.
 *
 * This driver provides access to OpenAI's new Responses API which offers:
 * - 3% better performance on reasoning tasks (SWE-bench)
 * - 40-80% improved cache utilization
 * - Built-in tools: web search, file search, code interpreter, computer use
 * - Server-side conversation state via `previous_response_id`
 * - Semantic streaming events (not raw deltas)
 *
 * Key differences from Chat Completions:
 * - Endpoint: `/v1/responses` instead of `/v1/chat/completions`
 * - Uses `input` instead of `messages`
 * - Uses `instructions` for system prompt
 * - Uses `max_output_tokens` instead of `max_completion_tokens`
 * - Response has `output[]` items array instead of `choices[0].message`
 * - Uses `status` instead of `finish_reason`
 *
 * @see https://platform.openai.com/docs/api-reference/responses
 */
class OpenAIResponsesDriver extends BaseInferenceDriver
{
    protected CanTranslateInferenceRequest $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->requestTranslator = new OpenAIResponsesRequestAdapter(
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
