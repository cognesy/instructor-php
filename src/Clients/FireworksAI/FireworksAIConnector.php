<?php
namespace Cognesy\Instructor\Clients\FireworksAI;

use Cognesy\Instructor\ApiClient\LLMConnector;

class FireworksAIConnector extends LLMConnector
{
    protected string $baseUrl = 'https://api.fireworks.ai/inference/v1';
}
