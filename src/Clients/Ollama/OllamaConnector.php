<?php
namespace Cognesy\Instructor\Clients\Ollama;

use Cognesy\Instructor\ApiClient\ApiConnector;

class OllamaConnector extends ApiConnector
{
    protected string $baseUrl = 'http://localhost:11434/v1';
}
