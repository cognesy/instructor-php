<?php
namespace Cognesy\Instructor\Clients\FireworksAI;

use Cognesy\Instructor\ApiClient\ApiConnector;

class FireworksAIConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.fireworks.ai/inference/v1';
}
