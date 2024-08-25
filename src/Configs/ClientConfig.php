<?php
namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Core\Factories\ModelFactory;
use Cognesy\Instructor\Events\EventDispatcher;

use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
//use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Azure\AzureClient;
use Cognesy\Instructor\Clients\Cohere\CohereClient;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
use Cognesy\Instructor\Clients\Gemini\GeminiClient;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIClient;

class ClientConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class: ApiRequestFactory::class,
            context: [
                'requestConfig' => $config->reference(ApiRequestConfig::class),
            ],
        );

        $config->object(
            class: ApiClientFactory::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultClient' => $config->reference(CanCallApi::class),
                'clients' => [
                    'anthropic' => $config->reference(AnthropicClient::class),
                    //'anyscale' => $config->reference(AnyscaleClient::class),
                    'azure' => $config->reference(AzureClient::class),
                    'cohere' => $config->reference(CohereClient::class),
                    'fireworks' => $config->reference(FireworksAIClient::class),
                    'gemini' => $config->reference(GeminiClient::class),
                    'groq' => $config->reference(GroqClient::class),
                    'mistral' => $config->reference(MistralClient::class),
                    'ollama' => $config->reference(OllamaClient::class),
                    'openai' => $config->reference(OpenAIClient::class),
                    'openrouter' => $config->reference(OpenRouterClient::class),
                    'together' => $config->reference(TogetherAIClient::class),
                ],
            ],
        );

        $config->object(
            class: ModelFactory::class,
            context: [
                'models' => [
//                    'anthropic:claude-3-haiku' => $config->reference('anthropic:claude-3-haiku'),
//                    'anthropic:claude-3-sonnet' => $config->reference('anthropic:claude-3-sonnet'),
//                    'anthropic:claude-3.5-sonnet' => $config->reference('anthropic:claude-3.5-sonnet'),
//                    'anthropic:claude-3-opus' => $config->reference('anthropic:claude-3-opus'),
//                    //'anyscale:mixtral-8x7b' => $config->reference('anyscale:mixtral-8x7b'),
//                    'azure:gpt-3.5-turbo' => $config->reference('azure:gpt-3.5-turbo'),
//                    'cohere:command-r' => $config->reference('cohere:command-r'),
//                    'cohere:command-r-plus' => $config->reference('cohere:command-r-plus'),
//                    'cohere:command' => $config->reference('cohere:command'),
//                    'cohere:command-light' => $config->reference('cohere:command-light'),
//                    'fireworks:mixtral-8x7b' => $config->reference('fireworks:mixtral-8x7b'),
//                    'google:gemini-1.5-flash' => $config->reference('google:gemini-1.5-flash'),
//                    'groq:llama3-8b' => $config->reference('groq:llama3-8b'),
//                    'groq:llama3-70b' => $config->reference('groq:llama3-70b'),
//                    'groq:mixtral-8x7b' => $config->reference('groq:mixtral-8x7b'),
//                    'groq:gemma-7b' => $config->reference('groq:gemma-7b'),
//                    'mistral:mistral-7b' => $config->reference('mistral:mistral-7b'),
//                    'mistral:mixtral-8x7b' => $config->reference('mistral:mixtral-8x7b'),
//                    'mistral:mixtral-8x22b' => $config->reference('mistral:mixtral-8x22b'),
//                    'mistral:mistral-small' => $config->reference('mistral:mistral-small'),
//                    'mistral:mistral-medium' => $config->reference('mistral:mistral-medium'),
//                    'mistral:mistral-large' => $config->reference('mistral:mistral-large'),
//                    'ollama:llama2' => $config->reference('ollama:llama2'),
//                    'openai:gpt-4o' => $config->reference('openai:gpt-4o'),
//                    'openai:gpt-4o-mini' => $config->reference('openai:gpt-4o-mini'),
//                    'openai:gpt-4-turbo' => $config->reference('openai:gpt-4-turbo'),
//                    'openai:gpt-4' => $config->reference('openai:gpt-4'),
//                    'openai:gpt-4-32k' => $config->reference('openai:gpt-4-32k'),
//                    'openai:gpt-3.5-turbo' => $config->reference('openai:gpt-3.5-turbo'),
//                    'openrouter:llama3' => $config->reference('openrouter:llama3'),
//                    'openrouter:mixtral-8x7b' => $config->reference('openrouter:mixtral-8x7b'),
//                    'openrouter:mistral-7b' => $config->reference('openrouter:mistral-7b'),
//                    'together:mixtral-8x7b' => $config->reference('together:mixtral-8x7b'),
                ],
                'allowUnknownModels' => true,
            ],
        );

    }
}