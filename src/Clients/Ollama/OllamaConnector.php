<?php
namespace Cognesy\Instructor\Clients\Ollama;

use Cognesy\Instructor\ApiClient\LLMConnector;

class OllamaConnector extends LLMConnector
{
    protected string $baseUrl = 'http://localhost:11434/v1';
}
