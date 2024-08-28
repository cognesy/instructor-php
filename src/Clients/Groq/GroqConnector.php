<?php
namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\ApiConnector;

class GroqConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.groq.com/openai/v1';
}
