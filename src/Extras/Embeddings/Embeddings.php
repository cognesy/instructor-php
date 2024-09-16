<?php

namespace Cognesy\Instructor\Extras\Embeddings;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Utils\Settings;
use GuzzleHttp\Client;

class Embeddings
{
    use Traits\HasFinders;
    use Traits\HasClients;

    protected Client $client;
    protected ClientType $clientType;
    protected string $model = '';
    protected int $dimensions = 0;
    protected string $apiKey = '';
    protected string $apiUrl = '';
    protected string $endpoint = '';
    protected int $maxInputs = 0;

    public function __construct() {
        $this->client = new Client();
        $client = Settings::get('embed', "defaultConnection");
        $this->loadConfig($client);
    }

    public function withClient(string $client) : self {
        $this->loadConfig($client);
        return $this;
    }

    public function withModel(string $model) : self {
        $this->model = $model;
        return $this;
    }

    public function clientType() : ClientType {
        return $this->clientType;
    }

    public function model() : string {
        return $this->model;
    }

    public function maxInputs() : string {
        return $this->apiKey;
    }

    public function dimensions() : int {
        return $this->dimensions;
    }
}
