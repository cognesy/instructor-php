<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\A21\A21BodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\Inference\Drivers\Azure\AzureDriver;
use Cognesy\Polyglot\Inference\Drivers\Bedrock\BedrockOpenAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Cerebras\CerebrasBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2Driver;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceDriver;
use Cognesy\Polyglot\Inference\Drivers\Inception\InceptionBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\InferenceDriverSpec;
use Cognesy\Polyglot\Inference\Drivers\Meta\MetaBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAIResponses\OpenAIResponsesDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesDriver;
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
     * TWO KINDS OF ENTRY, and the difference between them is the whole point of
     * instructor-eexl.9. A provider that speaks the OpenAI wire protocol is an
     * `InferenceDriverSpec` — a row of class names, with no class of its own. A provider that
     * assembles its own URL or headers keeps a driver class, because that is behaviour and a
     * table cannot hold it.
     *
     * To the registry both are just callables, which is why `withDriver()` still accepts a
     * class-string or a closure from outside exactly as it did before.
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

            // -- Bespoke request adapters: these assemble their own URL or headers ---------
            'anthropic' => AnthropicDriver::class,
            'azure' => AzureDriver::class,
            'bedrock-openai' => BedrockOpenAIDriver::class,
            'cohere' => CohereV2Driver::class,
            'gemini' => GeminiDriver::class,
            'gemini-oai' => GeminiOAIDriver::class,
            'huggingface' => HuggingFaceDriver::class,
            'openai-responses' => OpenAIResponsesDriver::class,
            'openresponses' => OpenResponsesDriver::class,
        ]);
    }
}
