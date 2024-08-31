<?php
namespace Cognesy\Instructor\Clients\OpenRouter;

use Cognesy\Instructor\ApiClient\LLMConnector;

class OpenRouterConnector extends LLMConnector
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';
}
