<?php
namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\LLMConnector;

class CohereConnector extends LLMConnector
{
    protected string $baseUrl = 'https://api.cohere.com/v1/';
}