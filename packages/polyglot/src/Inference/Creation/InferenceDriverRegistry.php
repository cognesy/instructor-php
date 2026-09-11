<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanProvideInferenceDrivers;
use Cognesy\Polyglot\Inference\Drivers\A21\A21BodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Azure\AzureOpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\Bedrock\BedrockOpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\Cerebras\CerebrasBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2BodyFormat;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2RequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\Inception\InceptionBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\InferenceDriverSpec;
use Cognesy\Polyglot\Inference\Drivers\Meta\MetaBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleReasoningAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAIResponses\OpenAIResponsesRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenRouter\OpenRouterBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Perplexity\PerplexityBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\SambaNova\SambaNovaBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\XAI\XAiMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;
use InvalidArgumentException;
use Override;

final class InferenceDriverRegistry implements CanProvideInferenceDrivers
{
    private static ?self $default = null;

    /** @param array<string, callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest> $drivers */
    private function __construct(
        private array $drivers = [],
    ) {}

    public static function make(): self {
        return new self();
    }

    public static function default(): self {
        return self::$default ??= self::fromArray(self::defaultSpecifications());
    }

    /**
     * Populates the table directly rather than folding `withDriver()` over it.
     *
     * `withDriver()` is a public wither and clones per call, which is correct for a wither and
     * wrong for a named constructor: building the 29-entry bundled map through it cost 29
     * clones of a growing array to produce one registry. This is the same object either way —
     * `toDriverFactory()` is still the only thing that turns an entry into a factory.
     *
     * @param array<string, string|callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest> $drivers
     */
    public static function fromArray(array $drivers): self {
        $factories = [];
        foreach ($drivers as $name => $driver) {
            $factories[$name] = self::toDriverFactory($driver);
        }

        return new self($factories);
    }

    /**
     * @param string|callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest $driver
     */
    public function withDriver(string $name, string|callable $driver): self {
        $copy = clone $this;
        $copy->drivers[$name] = self::toDriverFactory($driver);
        return $copy;
    }

    public function withoutDriver(string $name): self {
        $copy = clone $this;
        unset($copy->drivers[$name]);
        return $copy;
    }

    #[Override]
    public function has(string $name): bool {
        return isset($this->drivers[$name]);
    }

    /** @return array<string> */
    #[Override]
    public function driverNames(): array {
        return array_keys($this->drivers);
    }

    #[Override]
    public function makeDriver(
        string $name,
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        CanHandleEvents $events,
    ): CanProcessInferenceRequest {
        $factory = $this->drivers[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException("Provider type not supported - missing inference driver: {$name}");
        }

        return $factory($config, $httpClient, $events);
    }

    /**
     * @param string|callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest $driver
     * @return callable(LLMConfig,CanSendHttpRequests,CanHandleEvents):CanProcessInferenceRequest
     */
    private static function toDriverFactory(string|callable $driver): callable {
        return match (true) {
            is_callable($driver) => static function (LLMConfig $config, CanSendHttpRequests $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver factory must return ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
            is_string($driver) => static function (LLMConfig $config, CanSendHttpRequests $httpClient, CanHandleEvents $events) use ($driver): CanProcessInferenceRequest {
                $instance = new $driver($config, $httpClient, $events);
                if (!$instance instanceof CanProcessInferenceRequest) {
                    throw new InvalidArgumentException('Custom inference driver class must implement ' . CanProcessInferenceRequest::class);
                }

                return $instance;
            },
        };
    }

    /** @return array<string, InferenceDriverSpec> */
    private static function defaultSpecifications(): array {
        $openAiCompatible = new InferenceDriverSpec(
            bodyFormat: OpenAICompatibleBodyFormat::class,
            responseAdapter: OpenAICompatibleReasoningAdapter::class,
        );

        return [
            'a21' => new InferenceDriverSpec(bodyFormat: A21BodyFormat::class),
            'cerebras' => new InferenceDriverSpec(bodyFormat: CerebrasBodyFormat::class),
            'deepseek' => new InferenceDriverSpec(
                bodyFormat: DeepseekBodyFormat::class,
                responseAdapter: DeepseekResponseAdapter::class,
                reasoningWireFormat: ReasoningWireFormat::NamedEffort,
            ),
            'fireworks' => new InferenceDriverSpec(bodyFormat: FireworksBodyFormat::class),
            'glm' => new InferenceDriverSpec(
                bodyFormat: GlmBodyFormat::class,
                responseAdapter: GlmResponseAdapter::class,
                reasoningWireFormat: ReasoningWireFormat::BooleanThinking,
            ),
            'groq' => new InferenceDriverSpec(
                bodyFormat: GroqBodyFormat::class,
                usageFormat: GroqUsageFormat::class,
            ),
            'inception' => new InferenceDriverSpec(bodyFormat: InceptionBodyFormat::class),
            'meta' => new InferenceDriverSpec(bodyFormat: MetaBodyFormat::class),
            'minimaxi' => new InferenceDriverSpec(
                bodyFormat: MinimaxiBodyFormat::class,
                responseAdapter: MinimaxiResponseAdapter::class,
            ),
            'mistral' => new InferenceDriverSpec(
                bodyFormat: MistralBodyFormat::class,
                reasoningWireFormat: ReasoningWireFormat::NamedEffort,
            ),
            'openai' => new InferenceDriverSpec(
                bodyFormat: OpenAIBodyFormat::class,
                reasoningWireFormat: ReasoningWireFormat::NamedEffort,
            ),
            'openrouter' => new InferenceDriverSpec(
                bodyFormat: OpenRouterBodyFormat::class,
                reasoningWireFormat: ReasoningWireFormat::OpenRouter,
            ),
            'perplexity' => new InferenceDriverSpec(bodyFormat: PerplexityBodyFormat::class),
            'qwen' => new InferenceDriverSpec(
                bodyFormat: QwenBodyFormat::class,
                responseAdapter: QwenResponseAdapter::class,
                reasoningWireFormat: ReasoningWireFormat::Qwen,
            ),
            'sambanova' => new InferenceDriverSpec(bodyFormat: SambaNovaBodyFormat::class),
            'xai' => new InferenceDriverSpec(
                bodyFormat: OpenAICompatibleBodyFormat::class,
                messageFormat: XAiMessageFormat::class,
                reasoningWireFormat: ReasoningWireFormat::NamedEffort,
            ),
            'moonshot' => new InferenceDriverSpec(
                bodyFormat: OpenAICompatibleBodyFormat::class,
                reasoningWireFormat: ReasoningWireFormat::BooleanThinking,
            ),
            'ollama' => $openAiCompatible,
            'openai-compatible' => $openAiCompatible,
            'together' => $openAiCompatible,
            'anthropic' => new InferenceDriverSpec(
                bodyFormat: AnthropicBodyFormat::class,
                requestAdapter: AnthropicRequestAdapter::class,
                responseAdapter: AnthropicResponseAdapter::class,
                usageFormat: AnthropicUsageFormat::class,
                messageFormat: AnthropicMessageFormat::class,
                reasoningWireFormat: ReasoningWireFormat::Anthropic,
            ),
            'azure' => new InferenceDriverSpec(
                bodyFormat: OpenAIBodyFormat::class,
                requestAdapter: AzureOpenAIRequestAdapter::class,
            ),
            'bedrock-openai' => new InferenceDriverSpec(
                bodyFormat: OpenAICompatibleBodyFormat::class,
                requestAdapter: BedrockOpenAIRequestAdapter::class,
            ),
            'cohere' => new InferenceDriverSpec(
                bodyFormat: CohereV2BodyFormat::class,
                requestAdapter: CohereV2RequestAdapter::class,
                responseAdapter: CohereV2ResponseAdapter::class,
                usageFormat: CohereV2UsageFormat::class,
                reasoningWireFormat: ReasoningWireFormat::Cohere,
            ),
            'gemini' => new InferenceDriverSpec(
                bodyFormat: GeminiBodyFormat::class,
                requestAdapter: GeminiRequestAdapter::class,
                responseAdapter: GeminiResponseAdapter::class,
                usageFormat: GeminiUsageFormat::class,
                messageFormat: GeminiMessageFormat::class,
                reasoningWireFormat: ReasoningWireFormat::Gemini,
            ),
            'gemini-oai' => new InferenceDriverSpec(
                bodyFormat: GeminiOAIBodyFormat::class,
                requestAdapter: GeminiOAIRequestAdapter::class,
                usageFormat: GeminiOAIUsageFormat::class,
                reasoningWireFormat: ReasoningWireFormat::NamedEffort,
            ),
            'huggingface' => new InferenceDriverSpec(
                bodyFormat: HuggingFaceBodyFormat::class,
                requestAdapter: HuggingFaceRequestAdapter::class,
            ),
            'openai-responses' => new InferenceDriverSpec(
                bodyFormat: OpenResponsesBodyFormat::class,
                requestAdapter: OpenAIResponsesRequestAdapter::class,
                responseAdapter: OpenResponsesResponseAdapter::class,
                usageFormat: OpenResponsesUsageFormat::class,
                messageFormat: OpenResponsesMessageFormat::class,
                reasoningWireFormat: ReasoningWireFormat::OpenResponses,
            ),
            'openresponses' => new InferenceDriverSpec(
                bodyFormat: OpenResponsesBodyFormat::class,
                requestAdapter: OpenResponsesRequestAdapter::class,
                responseAdapter: OpenResponsesResponseAdapter::class,
                usageFormat: OpenResponsesUsageFormat::class,
                messageFormat: OpenResponsesMessageFormat::class,
            ),
        ];
    }
}
