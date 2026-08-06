<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
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
use Cognesy\Utils\Str;

final class BundledInferenceDrivers
{
    /**
     * The bundled table is a compile-time constant, so it is built once per process.
     *
     * Sharing the instance is safe precisely because `InferenceDriverRegistry` is immutable:
     * `withDriver()` and `withoutDriver()` return copies, so a caller that customises the
     * bundled registry gets its own object and cannot reach this one. There is no global
     * registration API that would let anyone mutate it either.
     *
     * Deliberately no reset hook. Nothing in the suite expects a fresh registry per call —
     * nothing *can*, since there is no way to mutate a registry in place — so a reset would
     * be a public method with no caller and no reachable purpose.
     */
    private static ?InferenceDriverRegistry $instance = null;

    /**
     * Every bundled provider is an `InferenceDriverSpec`: the row names the provider's
     * translators and capabilities, while `SpecifiedInferenceDriver` supplies the shared
     * execution lifecycle. OpenAI-compatible providers use the defaults where possible;
     * providers with native payloads or bespoke HTTP behavior name those collaborators in the
     * same row.
     *
     * To the registry bundled specs and custom registrations are still just callables, which is
     * why `withDriver()` continues to accept a class-string or a closure from outside.
     */
    public static function registry(): InferenceDriverRegistry {
        // Four names, one behaviour. They shared OpenAICompatibleDriver before and share one
        // spec object now; the names stay because configuration files reference them.
        $openAiCompatible = new InferenceDriverSpec(bodyFormat: OpenAICompatibleBodyFormat::class);

        return self::$instance ??= InferenceDriverRegistry::fromArray([
            // -- OpenAI wire protocol: declared, not coded ---------------------------------
            'a21' => new InferenceDriverSpec(
                bodyFormat: A21BodyFormat::class,
                capabilities: new DriverCapabilities(responseFormatJsonSchema: false),
            ),
            'cerebras' => new InferenceDriverSpec(
                bodyFormat: CerebrasBodyFormat::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'deepseek' => new InferenceDriverSpec(
                bodyFormat: DeepseekBodyFormat::class,
                responseAdapter: DeepseekResponseAdapter::class,
                // The one provider whose answer is not a constant: reasoner models drop tools
                // and structured output. See InferenceDriverSpec::$capabilities.
                capabilities: static function (string $model): DriverCapabilities {
                    $isReasoner = Str::contains($model, 'reasoner');
                    return new DriverCapabilities(
                        toolCalling: !$isReasoner,
                        toolChoice: !$isReasoner,
                        responseFormatJsonSchema: !$isReasoner,
                        responseFormatWithTools: false,
                    );
                },
            ),
            'fireworks' => new InferenceDriverSpec(
                bodyFormat: FireworksBodyFormat::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'glm' => new InferenceDriverSpec(
                bodyFormat: GlmBodyFormat::class,
                responseAdapter: GlmResponseAdapter::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'groq' => new InferenceDriverSpec(
                bodyFormat: GroqBodyFormat::class,
                usageFormat: GroqUsageFormat::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'inception' => new InferenceDriverSpec(bodyFormat: InceptionBodyFormat::class),
            'meta' => new InferenceDriverSpec(bodyFormat: MetaBodyFormat::class),
            'minimaxi' => new InferenceDriverSpec(
                bodyFormat: MinimaxiBodyFormat::class,
                responseAdapter: MinimaxiResponseAdapter::class,
            ),
            'mistral' => new InferenceDriverSpec(
                bodyFormat: MistralBodyFormat::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'openai' => new InferenceDriverSpec(bodyFormat: OpenAIBodyFormat::class),
            'openrouter' => new InferenceDriverSpec(
                bodyFormat: OpenRouterBodyFormat::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'perplexity' => new InferenceDriverSpec(
                bodyFormat: PerplexityBodyFormat::class,
                capabilities: new DriverCapabilities(
                    toolCalling: false,
                    toolChoice: false,
                    responseFormatWithTools: false,
                ),
            ),
            'qwen' => new InferenceDriverSpec(
                bodyFormat: QwenBodyFormat::class,
                responseAdapter: QwenResponseAdapter::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'sambanova' => new InferenceDriverSpec(
                bodyFormat: SambaNovaBodyFormat::class,
                capabilities: new DriverCapabilities(
                    responseFormatJsonSchema: false,
                    responseFormatWithTools: false,
                ),
            ),
            'xai' => new InferenceDriverSpec(
                bodyFormat: OpenAICompatibleBodyFormat::class,
                messageFormat: XAiMessageFormat::class,
            ),
            'moonshot' => $openAiCompatible,
            'ollama' => $openAiCompatible,
            'openai-compatible' => $openAiCompatible,
            'together' => $openAiCompatible,

            // -- Native protocols and bespoke HTTP adapters: still declarative --------------
            'anthropic' => new InferenceDriverSpec(
                bodyFormat: AnthropicBodyFormat::class,
                requestAdapter: AnthropicRequestAdapter::class,
                responseAdapter: AnthropicResponseAdapter::class,
                usageFormat: AnthropicUsageFormat::class,
                messageFormat: AnthropicMessageFormat::class,
                capabilities: new DriverCapabilities(
                    responseFormatJsonObject: false,
                    responseFormatJsonSchema: false,
                    responseFormatWithTools: false,
                ),
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
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'gemini' => new InferenceDriverSpec(
                bodyFormat: GeminiBodyFormat::class,
                requestAdapter: GeminiRequestAdapter::class,
                responseAdapter: GeminiResponseAdapter::class,
                usageFormat: GeminiUsageFormat::class,
                messageFormat: GeminiMessageFormat::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'gemini-oai' => new InferenceDriverSpec(
                bodyFormat: GeminiOAIBodyFormat::class,
                requestAdapter: GeminiOAIRequestAdapter::class,
                usageFormat: GeminiOAIUsageFormat::class,
                capabilities: new DriverCapabilities(
                    responseFormatJsonSchema: false,
                    responseFormatWithTools: false,
                ),
            ),
            'huggingface' => new InferenceDriverSpec(
                bodyFormat: HuggingFaceBodyFormat::class,
                requestAdapter: HuggingFaceRequestAdapter::class,
                capabilities: new DriverCapabilities(responseFormatWithTools: false),
            ),
            'openai-responses' => new InferenceDriverSpec(
                bodyFormat: OpenResponsesBodyFormat::class,
                requestAdapter: OpenAIResponsesRequestAdapter::class,
                responseAdapter: OpenResponsesResponseAdapter::class,
                usageFormat: OpenResponsesUsageFormat::class,
                messageFormat: OpenResponsesMessageFormat::class,
            ),
            'openresponses' => new InferenceDriverSpec(
                bodyFormat: OpenResponsesBodyFormat::class,
                requestAdapter: OpenResponsesRequestAdapter::class,
                responseAdapter: OpenResponsesResponseAdapter::class,
                usageFormat: OpenResponsesUsageFormat::class,
                messageFormat: OpenResponsesMessageFormat::class,
            ),
        ]);
    }
}
