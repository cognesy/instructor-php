<?php
namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\ApiConnector;

class MistralConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.mistral.ai/v1';
}
