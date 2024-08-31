<?php
namespace Cognesy\Instructor\Clients\TogetherAI;

use Cognesy\Instructor\ApiClient\LLMConnector;

class TogetherAIConnector extends LLMConnector
{
    protected string $baseUrl = 'https://api.together.xyz/v1';
}
