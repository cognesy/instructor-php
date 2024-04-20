<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Utils\Env;

trait HandlesApiClient
{
    protected CanCallApi $client;

    public function client() : CanCallApi {
        if (!isset($this->client)) {
            $this->client = $this->defaultClient();
        }
        return $this->client;
    }

    public function withClient(CanCallApi $client) : self {
        $this->client = $client;
        return $this;
    }

    protected function defaultClient() : CanCallApi {
        $apiKey = Env::get('OPENAI_API_KEY');
        return new OpenAIClient(apiKey: $apiKey);
    }
}
