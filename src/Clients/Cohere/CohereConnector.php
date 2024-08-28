<?php
namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\ApiConnector;

class CohereConnector extends ApiConnector
{
    protected string $baseUrl = 'https://api.cohere.com/v1/';
}