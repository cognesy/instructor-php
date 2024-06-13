<?php
namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;

use Cognesy\Instructor\Configs\Clients\AnthropicConfig;
use Cognesy\Instructor\Configs\Clients\AnyscaleConfig;
use Cognesy\Instructor\Configs\Clients\AzureConfig;
use Cognesy\Instructor\Configs\Clients\CohereConfig;
use Cognesy\Instructor\Configs\Clients\FireworksConfig;
use Cognesy\Instructor\Configs\Clients\GeminiConfig;
use Cognesy\Instructor\Configs\Clients\GroqConfig;
use Cognesy\Instructor\Configs\Clients\MistralConfig;
use Cognesy\Instructor\Configs\Clients\OllamaConfig;
use Cognesy\Instructor\Configs\Clients\OpenAIConfig;
use Cognesy\Instructor\Configs\Clients\OpenRouterConfig;
use Cognesy\Instructor\Configs\Clients\TogetherConfig;

class InstructorConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {
        $config->fromConfigProviders([
            new RequestHandlingConfig(),
            new ResponseHandlingConfig(),
            new ClientConfig(),
            new AnthropicConfig(),
            new AnyscaleConfig(),
            new AzureConfig(),
            new CohereConfig(),
            new FireworksConfig(),
            new GeminiConfig(),
            new GroqConfig(),
            new MistralConfig(),
            new OllamaConfig(),
            new OpenAIConfig(),
            new OpenRouterConfig(),
            new TogetherConfig(),
        ]);
    }
}
