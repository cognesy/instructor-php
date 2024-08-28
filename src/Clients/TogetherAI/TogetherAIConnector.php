<?php
namespace Cognesy\Instructor\Clients\TogetherAI;

use Cognesy\Instructor\ApiClient\ApiConnector;

class TogetherAIConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.together.xyz/v1';
}
