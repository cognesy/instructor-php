<?php
namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\LLMConnector;

class MistralConnector extends LLMConnector
{
    protected string $baseUrl = 'https://api.mistral.ai/v1';
}
