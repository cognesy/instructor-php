<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Polyglot\Inference\Drivers\A21\A21Driver;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\Inference\Drivers\Azure\AzureDriver;
use Cognesy\Polyglot\Inference\Drivers\Bedrock\BedrockOpenAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Cerebras\CerebrasDriver;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2Driver;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekDriver;
use Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksDriver;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\Inference\Drivers\GeminiOAI\GeminiOAIDriver;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmDriver;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqDriver;
use Cognesy\Polyglot\Inference\Drivers\HuggingFace\HuggingFaceDriver;
use Cognesy\Polyglot\Inference\Drivers\Inception\InceptionDriver;
use Cognesy\Polyglot\Inference\Drivers\Meta\MetaDriver;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiDriver;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAIResponses\OpenAIResponsesDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenRouter\OpenRouterDriver;
use Cognesy\Polyglot\Inference\Drivers\Perplexity\PerplexityDriver;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenDriver;
use Cognesy\Polyglot\Inference\Drivers\SambaNova\SambaNovaDriver;
use Cognesy\Polyglot\Inference\Drivers\XAI\XAiDriver;

final class BundledInferenceDrivers
{
    public static function registry(): InferenceDriverRegistry {
        return InferenceDriverRegistry::fromArray([
            'a21' => A21Driver::class,
            'anthropic' => AnthropicDriver::class,
            'azure' => AzureDriver::class,
            'bedrock-openai' => BedrockOpenAIDriver::class,
            'cerebras' => CerebrasDriver::class,
            'cohere' => CohereV2Driver::class,
            'deepseek' => DeepseekDriver::class,
            'fireworks' => FireworksDriver::class,
            'gemini' => GeminiDriver::class,
            'gemini-oai' => GeminiOAIDriver::class,
            'glm' => GlmDriver::class,
            'groq' => GroqDriver::class,
            'huggingface' => HuggingFaceDriver::class,
            'inception' => InceptionDriver::class,
            'meta' => MetaDriver::class,
            'minimaxi' => MinimaxiDriver::class,
            'mistral' => MistralDriver::class,
            'openai' => OpenAIDriver::class,
            'openai-responses' => OpenAIResponsesDriver::class,
            'openresponses' => OpenResponsesDriver::class,
            'openrouter' => OpenRouterDriver::class,
            'perplexity' => PerplexityDriver::class,
            'qwen' => QwenDriver::class,
            'sambanova' => SambaNovaDriver::class,
            'xai' => XAiDriver::class,
            'moonshot' => OpenAICompatibleDriver::class,
            'ollama' => OpenAICompatibleDriver::class,
            'openai-compatible' => OpenAICompatibleDriver::class,
            'together' => OpenAICompatibleDriver::class,
        ]);
    }
}
