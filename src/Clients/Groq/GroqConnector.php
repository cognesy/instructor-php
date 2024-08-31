<?php
namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\LLMConnector;

class GroqConnector extends LLMConnector
{
    protected string $baseUrl = 'https://api.groq.com/openai/v1';
}
